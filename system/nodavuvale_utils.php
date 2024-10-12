<?php
class Utils {

    public static function buildTreeData($rootId, $individuals, $relationships) {
        $treeData = [];
        

        // Step 1: Create lookup arrays for individuals and relationships
        $individualLookup = [];
        foreach ($individuals as $individual) {
            $individualLookup[$individual['id']] = $individual;
        }

        $relationshipLookup = [];
        foreach ($relationships as $rel) {
            if (!isset($relationshipLookup[$rel['individual_id_1']])) {
                $relationshipLookup[$rel['individual_id_1']] = [];
            }
            $relationshipLookup[$rel['individual_id_1']][] = $rel;
        }

        // Recursive function to build the tree nodes
        function addIndividualToTree($id, $relationshipLookup, $individualLookup, &$processedIds) {
            global $rootId;
            if (!isset($individualLookup[$id]) || in_array($id, $processedIds)) {
                return null;
            }
            $processedIds[] = $id;

            $individual = $individualLookup[$id];
            $birthYear = isset($individual['birth_year']) && $individual['birth_year'] !== 0 ? $individual['birth_year'] : '';
            $deathYear = isset($individual['death_year']) && $individual['death_year'] !== 0 ? $individual['death_year'] : '';
            //If $individual['first_names'] conatains more than one name, set $prefName to just the first of them
            $prefName = explode(" ", $individual['first_names'])[0];
            $briefName = $prefName." ".$individual['last_name'];
            $fullName = $individual['first_names'] . " " . $individual['last_name'];
            $lifeSpan = "($birthYear - $deathYear)";
            $gender = !empty($individual['gender']) ? $individual['gender'] : 'other';
            $editButton = '<button class="edit-btn" data-individual-id="' . $individual['id'] . '" title="Edit">&#9998;</button>';
            $addButton = '<button class="dropdown-btn" data-individual-id="' . $individual['id'] . '" data-individual-gender="'.$gender.'" title="Add relationship">&#128279;</button>';

            // Find parents of the root individual
            $parentLinks = '';
            if($id == $rootId) {
                $parents = findParents($id, $relationshipLookup, $individualLookup);
                if (!empty($parents)) {
                    // Use a span tag with inline-flex for the parent links
                    $parentLinks .= "<span class='parentLinks'>";
                    foreach ($parents as $parent) {
                        // Add parent links with an up symbol and inline-flex layout
                        $parentLinks .= "<span class='parent-link'><a href='?to=family/tree&root_id=" . $parent['id'] . "' title='Move up to ".$parent['name']."'>&#94;</a></span>";
                    }
                    $parentLinks .= "</span>";  // Add line break to separate from the rest of the content
                }
            }


            $node = [
                'id' => $individual['id'],
                'name' => $parentLinks . "<span class='nodeBodyText'><span class='bodyName' title='{$fullName}' onClick='window.location.href=\"?to=family/individual&individual_id=" . $individual['id']."\"' onDblClick='window.location.href=\"?to=family/tree&root_id=" . $individual['id'] . "\"'>" . $briefName . "</span><br /><span style='font-size: 0.7rem'>" . $lifeSpan . "</span></span>" . $editButton . $addButton,
                'class' => 'node treegender_'.$gender,
                'depthOffset' => 0,
            ];

            return $node;
        }

        // Function to find the parents of an individual
        function findParents($id, $relationshipLookup, $individualLookup) {
            $parents = [];
            foreach ($relationshipLookup as $relList) {
                foreach ($relList as $rel) {
                    if ($rel['relationship_type'] === 'child' && $rel['individual_id_2'] == $id) {
                        $parentId = $rel['individual_id_1'];
                        if (isset($individualLookup[$parentId])) {
                            $parent = $individualLookup[$parentId];
                            $parents[] = [
                                'id' => $parentId,
                                'name' => $parent['first_names']. " ". $parent['last_name'],
                            ];
                        }
                    }
                }
            }
            return $parents;
        }        

        // Check explicit spouse relationships and reverse relationships
        function findExplicitSpouses($id, $relationshipLookup) {
            $spouses = [];

            // Check if the individual has any explicit spouse relationships
            if (isset($relationshipLookup[$id])) {
                foreach ($relationshipLookup[$id] as $rel) {
                    if ($rel['relationship_type'] === 'spouse') {
                        $spouses[] = $rel['individual_id_2'];
                    }
                }
            }

            // Also check if the individual is marked as a spouse of someone else (reverse check)
            foreach ($relationshipLookup as $relList) {
                foreach ($relList as $rel) {
                    if ($rel['relationship_type'] === 'spouse' && $rel['individual_id_2'] === $id) {
                        $spouses[] = $rel['individual_id_1'];
                    }
                }
            }

            return array_unique($spouses);
        }

        // Recursive function to build marriage groups and explore all relationships
        function createMarriageGroup($id, $relationshipLookup, $individualLookup, &$processedIds) {
            // Add the individual to the tree
            $individualNode = addIndividualToTree($id, $relationshipLookup, $individualLookup, $processedIds);
            if (!$individualNode) return null;

            $marriages = [];
            $childrenWithBothParents = [];
            $childrenWithSingleParent = [];
            $existingSpouses = [];

            // Step 1: Find explicit spouses of the individual
            $explicitSpouses = findExplicitSpouses($id, $relationshipLookup);

            // Add all explicit spouses to the marriages list
            foreach ($explicitSpouses as $spouseId) {
                $spouseNode = addIndividualToTree($spouseId, $relationshipLookup, $individualLookup, $processedIds);
                if ($spouseNode) {
                    $marriages[] = [
                        'spouse' => $spouseNode,
                        'children' => [] // Children will be added later
                    ];
                    $existingSpouses[] = $spouseId;  // Track existing spouse IDs
                }
            }

            // Step 2: Find children and categorize them by parent count
            if (isset($relationshipLookup[$id])) {
                foreach ($relationshipLookup[$id] as $rel) {
                    if ($rel['relationship_type'] === 'child') {
                        $childId = $rel['individual_id_2'];
                        $childNode = createMarriageGroup($childId, $relationshipLookup, $individualLookup, $processedIds);
                        if ($childNode) {
                            $parentCount = countParents($childId, $relationshipLookup);

                            if ($parentCount === 2) {
                                // Check if both parents are already in marriages
                                $otherParent = findOtherParent($childId, $id, $relationshipLookup);
                                if ($otherParent && in_array($otherParent, $existingSpouses)) {
                                    // Add child to the correct marriage where both parents are present
                                    foreach ($marriages as &$marriage) {
                                        if ($marriage['spouse']['id'] === $otherParent) {
                                            $marriage['children'][] = $childNode;
                                            break;
                                        }
                                    }
                                } else {
                                    // If the other parent isn't in existing spouses, add a new marriage
                                    $spouseNode = addIndividualToTree($otherParent, $relationshipLookup, $individualLookup, $processedIds);
                                    if ($spouseNode) {
                                        $marriages[] = [
                                            'spouse' => $spouseNode,
                                            'children' => [$childNode]
                                        ];
                                        $existingSpouses[] = $otherParent;
                                    }
                                }
                            } else {
                                // Add child to the single parent (unknown spouse case)
                                $childrenWithSingleParent[] = $childNode;
                            }
                        }
                    }
                }
            }

            // Step 3: Add children with only one parent (unknown spouse)
            if (!empty($childrenWithSingleParent)) {
                $marriages[] = [
                    'spouse' => [
                        'id' => 'unknown_spouse_' . $id,
                        'name' => '<b>Unknown</b>'
                    ],
                    'children' => $childrenWithSingleParent
                ];
            }

            // Assign marriages to the individual node
            $individualNode['marriages'] = $marriages;

            // Sort spouses by number of children
            usort($individualNode['marriages'], function($a, $b) {
                return count($b['children']) - count($a['children']);
            });

            return $individualNode;
        }


        // Function to count the number of parents for a given child
        function countParents($childId, $relationshipLookup) {
            $parentCount = 0;
            foreach ($relationshipLookup as $relList) {
                foreach ($relList as $rel) {
                    if ($rel['relationship_type'] === 'child' && $rel['individual_id_2'] == $childId) {
                        $parentCount++;
                    }
                }
            }
            return $parentCount;
        }

        // Function to find the other parent for a child (besides the provided parentId)
        function findOtherParent($childId, $parentId, $relationshipLookup) {
            foreach ($relationshipLookup as $relList) {
                foreach ($relList as $rel) {
                    if ($rel['relationship_type'] === 'child' && $rel['individual_id_2'] == $childId && $rel['individual_id_1'] != $parentId) {
                        return $rel['individual_id_1'];  // Found the other parent
                    }
                }
            }
            return null;  // No other parent found
        }

        // Process the tree starting from the root and recursively expand all relationships
        $processedIds = [];
        $treeData[] = createMarriageGroup($rootId, $relationshipLookup, $individualLookup, $processedIds);

        return json_encode($treeData);
    }

    public static function getParents($individual_id) {
        // Get the database instance
        $db = Database::getInstance();
        
        // Fetch parents using the updated query
        $query = "
            SELECT individuals.* 
            FROM relationships 
            JOIN individuals ON relationships.individual_id_1 = individuals.id 
            WHERE relationships.individual_id_2 = ? 
            AND relationships.relationship_type = 'child'
        ";
        $parents = $db->fetchAll($query, [$individual_id]);
        
        return $parents;
    }

    public static function getChildren($individual_id) {
        // Get the database instance
        $db = Database::getInstance();
        
        // Fetch children using the updated query
        $query = "
            SELECT individuals.* 
            FROM relationships 
            JOIN individuals ON relationships.individual_id_2 = individuals.id 
            WHERE relationships.individual_id_1 = ? 
            AND relationships.relationship_type = 'child'
        ";
        $children = $db->fetchAll($query, [$individual_id]);
        
        return $children;
    }

    public static function getSpouses($individual_id) {
        // Get the database instance
        $db = Database::getInstance();
        
        $spouses=[];
        //First find explicit spouses as identified by the 'spouse' relationship type
        $esquery = "
            SELECT individuals.* 
            FROM relationships 
            JOIN individuals ON relationships.individual_id_2 = individuals.id 
            WHERE relationships.individual_id_1 = ? 
            AND relationships.relationship_type = 'spouse'";
        $explicitspouses = $db->fetchAll($esquery, [$individual_id]);
        foreach($explicitspouses as $spouse){
            $spouses[$spouse['id']]=$spouse;
        }


        //Then find implicit spouses as identified by the 'child' relationship type
        //First find any children of this person
        $cquery = "
            SELECT individuals.* 
            FROM relationships 
            JOIN individuals ON relationships.individual_id_2 = individuals.id 
            WHERE relationships.individual_id_1 = ? 
            AND relationships.relationship_type = 'child'";
        $children = $db->fetchAll($cquery, [$individual_id]);
        
        //Then find any OTHER parent of those children
        foreach($children as $child){
            $pquery = "
                SELECT individuals.* 
                FROM relationships 
                JOIN individuals ON relationships.individual_id_1 = individuals.id 
                WHERE relationships.individual_id_2 = ? 
                AND relationships.relationship_type = 'child'
                AND individuals.id != ?";
            $otherparent = $db->fetchAll($pquery, [$child['id'], $individual_id]);
            if($otherparent){
                foreach($otherparent as $op) {
                    $spouses[$op['id']]=$op;
                }
            }
        }        
        return $spouses;
    }

    public static function getSiblings($individual_id) {
        // Get the database instance
        $db = Database::getInstance();
        
        // Fetch siblings using the updated query
        $query = "
            SELECT distinct individuals.* 
            FROM relationships 
            JOIN individuals ON relationships.individual_id_2 = individuals.id 
            WHERE relationships.individual_id_1 IN (
                SELECT individual_id_1 
                FROM relationships 
                WHERE individual_id_2 = ? 
                AND relationship_type = 'child'
            ) 
            AND relationships.individual_id_2 != ? 
            AND relationships.relationship_type = 'child'
        ";
        $siblings = $db->fetchAll($query, [$individual_id, $individual_id]);
        
        return $siblings;
    }
}
