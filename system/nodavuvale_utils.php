<?php
class Utils {

    
    /**
     * Builds a hierarchical tree structure of individuals and their relationships.
     *
     * This function constructs a tree data structure starting from a given root individual.
     * It processes individuals and their relationships to build a nested representation of
     * family relationships, including parents, spouses, and children.
     *
     * @param int $rootId The ID of the root individual from which to start building the tree.
     * @param array $individuals An array of individuals, where each individual is an associative array with keys such as 'id', 'first_names', 'last_name', 'birth_year', 'death_year', 'gender', and 'keyimagepath'.
     * @param array $relationships An array of relationships, where each relationship is an associative array with keys such as 'individual_id_1', 'individual_id_2', and 'relationship_type'.
     * @return string A JSON-encoded string representing the hierarchical tree structure.
     *
     * The function performs the following steps:
     * 1. Creates lookup arrays for individuals and relationships for quick access.
     * 2. Defines a recursive function `addIndividualToTree` to add individuals to the tree.
     * 3. Defines helper functions `findParents`, `findExplicitSpouses`, `createMarriageGroup`, `countParents`, and `findOtherParent` to assist in building the tree structure.
     * 4. Processes the tree starting from the root individual and recursively expands all relationships.
     * 5. Returns the tree data as a JSON-encoded string.
     */
    public static function buildTreeData($rootId, $individuals, $relationships, $treesettings=array()) {
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

        $colors = ['genline_1', 'genline_2', 'genline_3', 'genline_4', 'genline_5', 'genline_6', 'genline_7', 'genline_8', 'genline_9', 'genline_10', 'genline_11', 'genline_12', 'genline_13', 'genline_14', 'genline_15', 'genline_16', 'genline_18', 'genline_19', 'genline_20'];

        // Recursive function to build the tree nodes
        function addIndividualToTree($id, $relationshipLookup, $individualLookup, &$processedIds, $generation, &$treeData, $treesettings, $color=null) {
            global $rootId;

            // Check if the generation limit is reached
            if (isset($treesettings['generationsShown']) && $treesettings['generationsShown'] !== 'All' && $generation > $treesettings['generationsShown']) {
                return null;
            }

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
            $lifeSpan = "$birthYear - $deathYear";
            $gender = !empty($individual['gender']) ? $individual['gender'] : 'other';
            $keyImage = !empty($individual['keyimagepath']) ? $individual['keyimagepath'] : 'images/default_avatar.webp';

            $parents = findParents($id, $relationshipLookup, $individualLookup);

            //Check if parents are already in the treeData
            $parentsInTree=false;
            foreach($parents as $parent) {
                if(in_array($parent['id'], $processedIds)) {
                    $parentsInTree=true;
                    break;
                }
            }

            $parentLink="";
            $imagepos="";
            //If there are no parents being displayed in the tree, or it's the first generation, and - in either case - the count of parents is greater than 0
            if((!$parentsInTree && $generation==1 && isset($parents) && count($parents) > 0) || (!$parentsInTree && isset($parents) && count($parents) > 0)) {
                $parentLinkId=$parents[0]['id'];
                $parentLink = '<div class="parents-link bg-transparent w-100 text-right -mr-0.5 mt-0.5 text-burnt-orange text-xs" style="z-index:1000" data-parents-in-tree="'.$parentsInTree.'" data-generation="'.$generation.'" data-count-parents="'.count($parents).'" title="View parents" onClick="window.location.href=\'?to=family/tree&root_id=' . $parentLinkId .'\'"><i class="fas fa-level-up-alt"></i></div>';
                //$parentLink = '<i class="fas fa-level-up-alt" style="z-index: 100">a</i>';
                $imagepos="margin-top:-18px";
            }

            $nodeBodyTemplate = "
            <input type='hidden' class='individualid' value='{individualId}'>
            <input type='hidden' class='individualgender' value='{individualGender}'>
            <input type='hidden' class='generation' value='{generation}'>
            {parentLink}
            <div class='nodeBodyText' style='{imagepos}'>
                <img src='{individualKeyImage}' class='nodeImage border object-cover cursor-pointer' title='See details for {individualFullName}' onclick='window.location.href=&apos;?to=family/individual&amp;individual_id={individualId}&apos;'>
                <span class='bodyName' id='treeindividualsname_{individualId}' title='See details for {individualFullName}' onclick='window.location.href=&apos;?to=family/individual&amp;individual_id={individualId}&apos;'>
                    {individualPrefName}<br>
                    {individualLastName}
                </span>
                <span style='font-size: 0.7rem'>
                    {individualLifeSpan}
                </span>                                  
            </div>
            <div class='w-100' style='width: 100px; margin-left:-5px'>
                <button class='text-sm md:text-md ft-edit-btn mr-3' title='Edit this individual' onclick='editIndividualFromTreeNode(&apos;{individualId}&apos;)'>
                    <i class='fas fa-edit text-ocean-blue'></i>
                </button>
                <button class='text-sm md:text-md ft-dropdown-btn mr-3' title='Add a relationship to this individual' onclick='addRelationshipToIndividualFromTreeNode(this)'>
                    <i class='fas fa-link text-ocean-blue'></i>
                </button>
                <button class='text-sm md:text-md ft-view-btn' title='Start tree at this individual' onclick='window.location.href=&apos;?to=family/tree&amp;root_id={individualId}&apos;'>
                    <i class='fas fa-arrow-right text-ocean-blue'></i>
                </button>        
            </div>            
          
            ";
            
            // Replace the template placeholders with actual values
            $nodeBodyText = str_replace(
                ['{parentLink}', '{individualId}', '{individualGender}', '{individualKeyImage}', '{individualFullName}', '{individualPrefName}', '{individualLastName}', '{individualLifeSpan}', '{imagepos}', '{generation}'],
                [$parentLink, $individual['id'], $gender, $keyImage, $fullName, $prefName, $individual['last_name'], $lifeSpan, $imagepos, $generation],
                $nodeBodyTemplate
            );
            if (isset($treesettings['colorScheme']) && $treesettings['colorScheme'] === 'firstGenLines' && $color !== null) {
                $colourclass = $color;
            } else {
                $colourclass = "treegender_".$gender;
            }
            $node = [
                'id' => $individual['id'],
                'name' => $nodeBodyText,
                'class' => 'node '.$colourclass.' generation_'.$generation,
                'depthOffset' => $treesettings['nodeSize'] ?? 1,
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
                                'gender' => $parent['gender'],
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
        function createMarriageGroup($id, $relationshipLookup, $individualLookup, &$processedIds, $generation, &$treeData, $treesettings, $colors, $color = null) {
            if (isset($treesettings['generationsShown']) && $treesettings['generationsShown'] !== 'All' && $generation > $treesettings['generationsShown']) {
                return null;
            }
            
            // Add the individual to the tree
            $individualNode = addIndividualToTree($id, $relationshipLookup, $individualLookup, $processedIds, $generation, $treeData, $treesettings, $color);
            if (!$individualNode) return null;

            $marriages = [];
            $childrenWithBothParents = [];
            $childrenWithSingleParent = [];
            $existingSpouses = [];

            // Step 1: Find explicit spouses of the individual
            $explicitSpouses = findExplicitSpouses($id, $relationshipLookup);

            // Add all explicit spouses to the marriages list
            foreach ($explicitSpouses as $spouseId) {
                $spouseNode = addIndividualToTree($spouseId, $relationshipLookup, $individualLookup, $processedIds, $generation, $treeData, $treesettings, $color);
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
                        // Assign a different color to each child of the root individual
                        $childColor = $color;
                        if ($generation == 1 && isset($treesettings['colorScheme']) && $treesettings['colorScheme'] === 'firstGenLines') {
                            $childColor = array_shift($colors);
                        }
                        $childNode = createMarriageGroup($childId, $relationshipLookup, $individualLookup, $processedIds, $generation + 1, $treeData, $treesettings, $colors, $childColor);
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
                                    $spouseNode = addIndividualToTree($otherParent, $relationshipLookup, $individualLookup, $processedIds, $generation, $treeData, $treesettings, $childColor);
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

                $unknownNode="
            <input type='hidden' class='individualid' value=''>
            <input type='hidden' class='individualgender' value='other'>
            <input type='hidden' class='generation' value=''>
            <div class='nodeBodyText opacity-70'>
                <img src='images/default_avatar.webp' class='nodeImage border object-cover opacity-50' title='Unknown parent'>
                <span class='bodyName'>
                    Unknown
                </span>
            </div>
            ";
                $marriages[] = [
                    'spouse' => [
                        'id' => 'unknown_spouse_' . $id,
                        'name' => $unknownNode,
                        'class' => 'node treegender_other generation_'.$generation,
                        'depthOffset' => $treesettings['nodeSize'] ?? 1,
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
        $treeData[] = createMarriageGroup($rootId, $relationshipLookup, $individualLookup, $processedIds, 1, $treeData, $treesettings, $colors );

        return json_encode($treeData);

    }

    public static function getLineOfDescendancy($topId, $bottomId) {
        if((!$topId || !$bottomId) || (!is_numeric($topId) || !is_numeric($bottomId))) {
            return array();
        }
        $sql="WITH RECURSIVE lineage_path AS (
                -- Base case: Start with the given individual and include their immediate parent
                SELECT 
                    i.id,
                    i.first_names,
                    i.last_name,
                    r.individual_id_2 AS parent_id,
                    CONCAT(SUBSTRING_INDEX(i.first_names, ' ', 1), ' ', i.last_name, '*', i.id) AS path
                FROM 
                    individuals i
                LEFT JOIN 
                    relationships r ON i.id = r.individual_id_2
                WHERE 
                    i.id = ? -- Replace with the starting individual's ID
                
                UNION ALL
                
                -- Recursive step: Find the parents and build the path up the lineage
                SELECT 
                    parent.id,
                    parent.first_names,
                    parent.last_name,
                    rel.individual_id_1 AS parent_id,
                    CONCAT(SUBSTRING_INDEX(parent.first_names, ' ', 1), ' ', parent.last_name, '*', rel.individual_id_1,'|', lp.path)
                FROM 
                    relationships rel
                JOIN 
                    lineage_path lp ON rel.individual_id_2 = lp.parent_id
                JOIN 
                    individuals parent ON rel.individual_id_1 = parent.id
                WHERE 
                    rel.relationship_type = 'child'
            )
            SELECT path
            FROM lineage_path
            WHERE parent_id IS NULL OR id = ?; -- Replace with Soli Nataleira's ID (1)

        ";
        $params=[ $bottomId, $topId ];
        //echo $sql."<br />";print_r($params);
        $db = Database::getInstance();
        $result = $db->fetchOne($sql, $params);
        //echo "<pre>Results: ";print_r($result);echo "</pre>";
        if(isset($result['path'])) {
            $output=array();
            $temp=explode("|",$result['path']);
            foreach($temp as $t) {
                $temp2=explode("*",$t);
                //print_r($temp2);
                $output[]=$temp2;
            }
            return $output;

        } else {
            return array();
        }
    }

    public static function getAscendancyPath($individualId) {
        if (!$individualId || !is_numeric($individualId)) {
            return array();
        }
    
        $sql = "WITH RECURSIVE lineage AS (
                    -- Start with the individual as the base of the lineage (generation 0)
                    SELECT 
                        p.id AS ancestor_id,
                        p.first_names AS ancestor_first_names,
                        p.last_name AS ancestor_last_name,
                        p.gender AS ancestor_gender,
                        p.id AS parent_id,
                        1 AS generation
                    FROM 
                        individuals i
                    LEFT JOIN 
                        relationships r ON i.id = r.individual_id_2 AND r.relationship_type = 'child'
                    INNER JOIN 
                        individuals p ON r.individual_id_1 = p.id
                        
                    WHERE 
                        i.id = ?
    
                    UNION ALL
    
                    -- Recursive step: Move up through each parent-child relationship
                    SELECT 
                        parent.id AS ancestor_id,
                        parent.first_names AS ancestor_first_names,
                        parent.last_name AS ancestor_last_name,
                        parent.gender AS ancestor_gender,
                        rel.individual_id_1 AS parent_id,
                        lineage.generation + 1 AS generation
                    FROM 
                        relationships rel
                    JOIN 
                        lineage ON rel.individual_id_2 = lineage.parent_id
                    JOIN 
                        individuals parent ON rel.individual_id_1 = parent.id
                    WHERE 
                        rel.relationship_type = 'child' 
                        AND lineage.ancestor_id != rel.individual_id_1 -- Avoid re-selecting ancestors

                )
                SELECT 
                    ancestor_id,
                    ancestor_first_names,
                    ancestor_last_name,
                    ancestor_gender,
                    generation
                FROM lineage
                ORDER BY generation ASC;
        ";

        $params = [$individualId];
        $db = Database::getInstance();
        $result = $db->fetchAll($sql, $params);

        //Add the individual to the start of the array
        $sql = "SELECT id as ancestor_id, first_names as ancestor_first_names, last_name as ancestor_last_name, gender as ancestor_gender, 0 as generation 
                FROM individuals 
                WHERE id = ?";
        $params = [$individualId];
        $individual = $db->fetchOne($sql, $params);
        array_unshift($result, $individual);


        return $result;
    }
    
    public static function getCommonAncestor($primaryId, $secondaryId) {
        // Get ascendancy paths for both individuals
        $primaryPath = self::getAscendancyPath($primaryId);
        $secondaryPath = self::getAscendancyPath($secondaryId);

        //echo "<pre>"; print_r($primaryPath); echo "</pre>";
        //echo "<pre>"; print_r($secondaryPath); echo "</pre>";
    
        // Convert the secondary path to a map for quick lookup
        $secondaryAncestors = [];
        foreach ($secondaryPath as $ancestor) {
            $secondaryAncestors[$ancestor['ancestor_id']] = $ancestor;
        }
    
        // Find the first common ancestor by iterating through the primary path
        foreach ($primaryPath as $ancestor) {
            if (isset($secondaryAncestors[$ancestor['ancestor_id']])) {
                // Return the first common ancestor found
                $commonAncestor = [
                    'common_ancestor_id' => $ancestor['ancestor_id'],
                    'common_ancestor_first_names' => $ancestor['ancestor_first_names'],
                    'common_ancestor_last_name' => $ancestor['ancestor_last_name'],
                    'common_ancestor_gender' => $ancestor['ancestor_gender'],
                    'individual_1_generations_from_ancestor' => $ancestor['generation'],
                    'individual_2_generations_from_ancestor' => $secondaryAncestors[$ancestor['ancestor_id']]['generation']
                ];
                return $commonAncestor;
            }
        }
    
        // If no common ancestor is found, return an empty array
        return array();
    }

    public static function getRelationshipLabel($primaryId, $secondaryId) {
        // Get ascendancy paths for both individuals
        if($primaryId==$secondaryId) {
            return "";
        }
        
        $primaryPath = self::getAscendancyPath($primaryId);
        $secondaryPath = self::getAscendancyPath($secondaryId);
        
        $sql = "SELECT gender FROM individuals WHERE id = ?";
        $db = Database::getInstance();
        $primaryGender = $db->fetchOne($sql, [$primaryId])['gender'];
        $secondaryGender = $db->fetchOne($sql, [$secondaryId])['gender'];

        // Convert the secondary path to a map for quick lookup
        $secondaryAncestors = [];
        foreach ($secondaryPath as $ancestor) {
            $secondaryAncestors[$ancestor['ancestor_id']] = $ancestor;
        }
    
        // Find the first common ancestor by iterating through the primary path
        foreach ($primaryPath as $ancestor) {
            if (isset($secondaryAncestors[$ancestor['ancestor_id']])) {
                // Return the first common ancestor found
                $commonAncestor = [
                    'common_ancestor_id' => $ancestor['ancestor_id'],
                    'common_ancestor_first_names' => $ancestor['ancestor_first_names'],
                    'common_ancestor_last_name' => $ancestor['ancestor_last_name'],
                    'common_ancestor_gender' => $ancestor['ancestor_gender'],
                    'individual_1_generations_from_ancestor' => $ancestor['generation'],
                    'individual_2_generations_from_ancestor' => $secondaryAncestors[$ancestor['ancestor_id']]['generation'],
                ];
                break;
            }
        }
    
        // If no common ancestor is found, return an empty string
        if (!isset($commonAncestor)) {
            return '';
        }
    
        // Generations removed from the common ancestor for each individual
        $gen1 = $commonAncestor['individual_1_generations_from_ancestor'];
        $gen2 = $commonAncestor['individual_2_generations_from_ancestor'];
    
        // Direct descendant relationships (Child, Grandchild, Great-grandchild, etc.)
        if ($commonAncestor['common_ancestor_id'] == $primaryId) {
            $relativeLabel = 'child';
            if($secondaryGender == "female") {
                $relativeLabel = 'daughter';
            } elseif($secondaryGender == "male") {
                $relativeLabel = 'son';
            }
            $generationsApart = $gen2 - $gen1;
            if ($generationsApart == 2) {
                $relativeLabel = 'grand' . $relativeLabel;
            } elseif ($generationsApart > 2) {
                $relativeLabel = str_repeat('great ', $generationsApart - 2) . 'grandchild';
            }
            return ucfirst($relativeLabel);
        } elseif ($commonAncestor['common_ancestor_id'] == $secondaryId) {
            $relativeLabel = 'parent';
            if ($commonAncestor['common_ancestor_gender'] == "female") {
                $relativeLabel = 'mother';
            } elseif ($commonAncestor['common_ancestor_gender'] == "male") {
                $relativeLabel = 'father';
            }
            $generationsApart = $gen1 - $gen2;
            if ($generationsApart == 2) {
                $relativeLabel = 'grand' . $relativeLabel;
            } elseif ($generationsApart > 2) {
                $relativeLabel = str_repeat('great ', $generationsApart - 2) . 'grand' . $relativeLabel;
            }
            return ucfirst($relativeLabel);
        }
    
        // Sibling relationship (same generation, common parent)
        if ($gen1 == 1 && $gen2 == 1) {
            $relativeLabel = 'sibling';
            if($secondaryGender == "female") {
                $relativeLabel = 'sister';
            } elseif($secondaryGender == "male") {
                $relativeLabel = 'brother';
            }
            return ucfirst($relativeLabel);
        }





    
        // Uncle/Aunt and Great-Uncle/Great-Aunt relationship
        if ($gen1 == 2 && $gen2 == 1) {
            $relativeLabel = "pibling (aunt/uncle)";
            if($secondaryGender=="female") {
                $relativeLabel = "aunt";
            } elseif($secondaryGender=="male") {
                $relativeLabel = "uncle";
            }
            return ucfirst($relativeLabel);
        }
        if ($gen1 == 1 && $gen2 == 2) {
            $relativeLabel = "nibling (niece/nephew)";
            if($secondaryGender=="female") {
                $relativeLabel = "niece";
            } elseif($secondaryGender=="male") {
                $relativeLabel = "nephew";
            }
            return ucfirst($relativeLabel);
        }


        

    
        // Cousin relationship
        if ($gen1 == $gen2) {
            $cousinLevel = $gen1 - 1;
            $suffix = match ($cousinLevel) {
                1 => 'st',
                2 => 'nd',
                3 => 'rd',
                default => 'th'
            };
            return ucfirst("{$cousinLevel}{$suffix} cousin");
        }     
    
        // "Removed" cousins
        $minGen = min($gen1, $gen2);
        $removed = abs($gen1 - $gen2);
        $cousinLevel = $minGen - 1;
        $suffix = match ($cousinLevel) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th'
        };
        $removedText = match ($removed) {
            1 => 'once removed',
            2 => 'twice removed',
            default => "{$removed} times removed"
        };
        if($cousinLevel==0) {
            $relativeLabel = "pibling (aunt/uncle)";
            if($secondaryGender=="female") {
                $relativeLabel = "aunt";
            } elseif($secondaryGender=="male") {
                $relativeLabel = "uncle";
            }
            $generationsApart = $gen1 - $gen2;
            $relativeLabel = str_repeat('great ', $generationsApart - 1) . $relativeLabel;
            return ucfirst($relativeLabel);
        }
        return ucfirst("{$cousinLevel}{$suffix} cousin {$removedText}");
    }
    
            

    public static function getIndividual($individualId) {
        $db = Database::getInstance();
        $query = "SELECT * FROM individuals WHERE id = ?";
        $individual = $db->fetchOne($query, [$individualId]);
        return $individual;
    }

    /**
     * Fetches a simple list of individuals from the database.
     * 
     */
    public static function getIndividualsList() {
        $db = Database::getInstance();
        $query = "SELECT id, first_names, last_name FROM individuals ORDER BY last_name, first_names";
        $individuals = $db->fetchAll($query);
        return $individuals;
    }

    public static function getParents($individual_id) {
        // Get the database instance
        $db = Database::getInstance();
        
        // Fetch parents using the updated query
        $query = "
            SELECT individuals.*, 
                COALESCE(
                    (SELECT files.file_path 
                        FROM file_links 
                        JOIN files ON file_links.file_id = files.id 
                        JOIN items ON items.item_id = file_links.item_id 
                        WHERE file_links.individual_id = individuals.id 
                        AND items.detail_type = 'Key Image'
                        LIMIT 1), 
                    '') AS keyimagepath,
                relationships.id as relationshipId
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
            SELECT individuals.*, 
                COALESCE(
                    (SELECT files.file_path 
                        FROM file_links 
                        JOIN files ON file_links.file_id = files.id 
                        JOIN items ON items.item_id = file_links.item_id 
                        WHERE file_links.individual_id = individuals.id 
                        AND items.detail_type = 'Key Image'
                        LIMIT 1), 
                    '') AS keyimagepath,
                relationships.id as relationshipId
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
        $esquery = "SELECT DISTINCT
                        CASE 
                            WHEN r.individual_id_1 = ? THEN r.individual_id_2 
                            ELSE r.individual_id_1 
                        END AS parent_id,
                        individual_spouse.*, 
                        keyImages.file_path AS keyimagepath,
                        r.id as relationshipId
                    FROM
                        relationships AS r
                    INNER JOIN individuals AS individual_spouse ON 
                        (CASE 
                            WHEN r.individual_id_1 = ? THEN r.individual_id_2 
                            ELSE r.individual_id_1 
                        END) = individual_spouse.id
                    LEFT JOIN (
                        SELECT files.file_path, file_links.individual_id
                        FROM files
                        INNER JOIN file_links ON files.id = file_links.file_id
                        INNER JOIN items ON file_links.item_id = items.item_id
                        WHERE items.detail_type = 'Key Image'
                    ) as keyImages ON keyImages.individual_id = individual_spouse.id
                    WHERE 
                        (r.individual_id_1 = ? OR r.individual_id_2 = ?)
                        AND r.relationship_type = 'spouse';
            ";
        $explicitspouses = $db->fetchAll($esquery, [$individual_id, $individual_id, $individual_id, $individual_id]);
        foreach($explicitspouses as $spouse){
            $spouses[$spouse['id']]=$spouse;
        }


        //Then find implicit spouses as identified by the 'child' relationship type
        //First find any children of this person
        $cquery = "
            SELECT distinct individuals.*, files.file_path as keyimagepath,
                relationships.id as relationshipId
            FROM relationships 
            JOIN individuals ON relationships.individual_id_2 = individuals.id 
            LEFT JOIN file_links ON file_links.individual_id=individuals.id 
            LEFT JOIN files ON file_links.file_id=files.id 
            LEFT JOIN items ON items.item_id=file_links.item_id AND items.detail_type='Key Image'
            WHERE relationships.individual_id_1 = ? 
            AND relationships.relationship_type = 'child'";
        $children = $db->fetchAll($cquery, [$individual_id]);
        
        //Then find any OTHER parent of those children
        foreach($children as $child){
            $pquery = "
                SELECT distinct individuals.*, files.file_path as keyimagepath,
                    relationships.id as relationshipId, relationships.id as relationshipId
                FROM relationships 
                JOIN individuals ON relationships.individual_id_1 = individuals.id 
                LEFT JOIN file_links ON file_links.individual_id=individuals.id 
                LEFT JOIN files ON file_links.file_id=files.id 
                LEFT JOIN items ON items.item_id=file_links.item_id AND items.detail_type='Key Image'
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
            SELECT distinct individuals.*, 
                COALESCE(
                    (SELECT files.file_path 
                        FROM file_links 
                        JOIN files ON file_links.file_id = files.id 
                        JOIN items ON items.item_id = file_links.item_id 
                        WHERE file_links.individual_id = individuals.id 
                        AND items.detail_type = 'Key Image'
                        LIMIT 1), 
                    '') AS keyimagepath,
                relationships.id as relationshipId
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
        $siblingsquery = $db->fetchAll($query, [$individual_id, $individual_id]);
        //Make sure we don't have duplicates
        $siblings=[];
        foreach($siblingsquery as $sibling) {
            $siblings[$sibling['id']]=$sibling;
        }
        
        return $siblings;
    }

    public static function getKeyImage($individual_id) {
        $db = Database::getInstance();
        $query = "SELECT files.file_path 
                    FROM file_links 
                    JOIN files ON file_links.file_id = files.id 
                    JOIN items ON items.item_id = file_links.item_id 
                    WHERE file_links.individual_id = ? 
                    AND items.detail_type = 'Key Image'
                    LIMIT 1";
        $keyimage = $db->fetchOne($query, [$individual_id]);
        if(!$keyimage) {
            return "images/default_avatar.webp";
        } else {
            return $keyimage['file_path'];
        }
    }

    /**
     * Get the items for an individual.
     * 
     * @param int $individual_id The ID of the individual.
     * @param string $since The date from which to fetch items.
     * @param string $order The order in which to fetch items. eg: "items.item_identifier ASC, items.updated ASC"
     */
    public static function getItems($individual_id, $since='1900-01-01 00:00:00', $order='item_identifier ASC, items.updated ASC') {
        // Get the database instance
        $db = Database::getInstance();
        // Fetch items using the updated query
        if($order) {
            $order = "ORDER BY ".$order;
        }

        $individualtypes=[];
        $itemstyles=self::getItemStyles();
        foreach($itemstyles as $key=>$val) {
            if($val=='individual') {
                $individualtypes[]=$key;
            }
        }
        //Build a comma delimited string of individual types
        $individualtypes="'".implode("','",$individualtypes)."'";

        //echo $individualtypes;

        $query = "
            SELECT item_links.id, item_groups.item_group_name, item_links.individual_id as item_links_individual_id, item_links.item_id as item_links_item_id,
				items.detail_value as items_item_value, items.updated as item_updated,
                others.first_names as other_first_names, others.last_name as other_last_name,
                users.first_name, users.last_name,
                individuals.first_names as tree_first_names, individuals.last_name as tree_last_name, individuals.id as individualId,
                CONCAT(individuals.birth_year,'-',individuals.birth_month,'-',individuals.birth_date) as tree_birth_date,
                CONCAT(individuals.death_year,'-',individuals.death_month,'-',individuals.death_date) as tree_death_date,
                IFNULL(items.item_identifier, UUID_SHORT()) as unique_id,
                CASE 
                    WHEN 
                        items.detail_type IN ('Spouse','Person') AND item_links.individual_id != items.detail_value
                        THEN CONCAT(others.first_names, ' ', others.last_name)
                    WHEN
                        items.detail_type IN ('Spouse','Person') AND item_links.individual_id = items.detail_value
                        THEN CONCAT(others.first_names, ' ', others.last_name) 
                    ELSE NULL
                END AS individual_name,
                CASE 
                    WHEN 
                        items.detail_type IN ('Spouse','Person') AND item_links.individual_id != items.detail_value
                        THEN items.detail_value
                    WHEN
                        items.detail_type IN ('Spouse','Person') AND item_links.individual_id = items.detail_value
                        THEN others.id 
                    
                    ELSE NULL
                END AS individual_name_id,
                items.*,  files.id as file_id, files.*
            FROM item_links 
            INNER JOIN items ON items.item_id=item_links.item_id
            INNER JOIN individuals ON item_links.individual_id=individuals.id
            LEFT JOIN file_links ON items.item_id = file_links.item_id
            LEFT JOIN files ON file_links.file_id = files.id
            LEFT JOIN item_groups ON items.item_identifier = item_groups.item_identifier
            LEFT JOIN users ON items.user_id = users.id
            LEFT JOIN item_links AS duplicateItems ON items.item_id = duplicateItems.item_id AND duplicateItems.id != item_links.id
            LEFT JOIN individuals AS others ON duplicateItems.individual_id = others.id
            WHERE item_links.individual_id like ?
            AND items.updated > ?
            $order 
        ";
        //echo "<pre>".$query; echo $individual_id; echo $since; echo "</pre>";
        $items = $db->fetchAll($query, [$individual_id, $since]);
        //echo "<pre>"; print_r($items); echo "</pre>";

        // Group items by item_identifier - if there is none, treat as individual groups AND and their linked individual_id
        $groupedItems = [];
        foreach ($items as $item) {
            //This has to be the item_identifier number - so that mutliple events of the same type are all displayed
            $itemIdentifier = $item['unique_id'];
            $key = $itemIdentifier.'_'.$item['item_links_individual_id'];
            //echo "<pre>"; print_r($item); echo "</pre>";
            //echo $key."<br />";
            $itemGroupName = $item['item_group_name'] ? $item['item_group_name'] : $item['detail_type'];
            if (!isset($groupedItems[$key])) {
                $groupedItems[$key] = []; //Create empty array for new item group
                $groupedItems[$key]['item_group_name'] = $itemGroupName;
            }
            if(!empty($item['detail_value'])) {
                $groupedItems[$key]['items'][] = $item;
            }
        }

        //If this is for a specific individualId, then iterate through the $groupedItems array 
        // and find any which have date values, then sort the top level of the array 
        // by that date with earliest first
        // Note that if the item_group_name is "Birth" or "Death" we should also use 
        // the "tree_birth_date" and "tree_death_date" values
        // Put any other top level items at the end of the array

        //Get the items.detail_type values which are dates from the $itemstyles array
        $dateTypes=[];
        foreach($itemstyles as $key=>$val) {
            if($val=='date') {
                //echo "Adding $key";
                $dateTypes[]=$key;
            }
        }
         
        if($individual_id && $individual_id != "%") {
            $sortedItems=[];
            foreach($groupedItems as $key=>$group) {
                $sortDate=date('Y-m-d');
                $groupType=$group['item_group_name'];
                //Iterate through the items looking for dates, and set the sortDate.
                // If there are two dates, use the one with the $item['detail_type'] = "Started"
                foreach($group['items'] as $item) {
                    if(in_array($item['detail_type'], $dateTypes) && $item['detail_type'] != 'Ended') {
                        $sortDate=$item['items_item_value'];
                        break;
                    }
                }



                if($groupType=='Birth') {
                    $sortDate=date("Y-m-d", strtotime($item['tree_birth_date']));
                    //Add an extra item to the $groupedItems[$key]['items'] called "Date" with the value of the birth date
                    $groupedItems[$key]['items'][]=array(
                        'id'=>null,
                        'item_group_name'=>'Birth',
                        'item_links_individual_id'=>$individual_id,
                        'item_links_item_id'=>null,
                        'item_updated'=>null,
                        'individualId'=>$individual_id,
                        'item_id'=>null,
                        'detail_type'=>'Date',
                        'detail_value'=>$sortDate,
                        'items_item_value'=>'Birth Date',
                        'item_identifier'=>$groupedItems[$key]['items'][0]['item_identifier'],
                        'unique_id'=>$groupedItems[$key]['items'][0]['unique_id'],
                    );

                }
                if($groupType=='Death') {
                    $sortDate=date("Y-m-d", strtotime($item['tree_death_date']));
                    //Add an extra item to the $groupedItems[$key]['items'] called "Date" with the value of the death date
                    $groupedItems[$key]['items'][]=array(
                        'id'=>null,
                        'item_group_name'=>'Death',
                        'item_links_individual_id'=>$individual_id,
                        'item_links_item_id'=>null,
                        'item_updated'=>null,
                        'individualId'=>$individual_id,
                        'item_id'=>null,
                        'detail_type'=>'Date',
                        'detail_value'=>$sortDate,
                        'items_item_value'=>'Death Date',
                        'item_identifier'=>$groupedItems[$key]['items'][0]['item_identifier'],
                        'unique_id'=>$groupedItems[$key]['items'][0]['unique_id'],
                    );
                }
                //Create a new sortdate variable and default it to today (put everything at the end)
                $groupedItems[$key]['sortDate']=$sortDate;
                $sortedItems[$key]=$sortDate;
            }
            array_multisort($sortedItems, SORT_ASC, $groupedItems);
        }

        return $groupedItems;
    }

    /**
     * Get the files for an individual.
     * 
     * @param int $individual_id The ID of the individual.
     * @param string $file_type The type of file to fetch.
     * @return array An array of files for the individual.
     */
    public static function getFiles($individual_id, $file_type='%') {
        // Get the database instance
        $db = Database::getInstance();
        
        // Fetch files using the updated query
        $query = "
            SELECT files.*, file_links.item_id, users.first_name, users.last_name
            FROM files 
            JOIN file_links ON files.id = file_links.file_id 
            LEFT JOIN users ON files.user_id = users.id
            WHERE file_links.individual_id = ?
            AND file_type like ?
        ";
        $files = $db->fetchAll($query, [$individual_id, $file_type]);
        
        return $files;
        
    }

    /**
     * Get the discussions for an individual.
     * 
     * @param int $individual_id The ID of the individual.
     * @return array An array of discussions for the individual.
     */
    public static function getIndividualDiscussions($individual_id) {
        // Get the database instance
        $db = Database::getInstance();
        
        // Fetch discussions using the updated query
        $query = "
            SELECT discussions.*, users.first_name, users.last_name, users.avatar
            FROM discussions 
            JOIN users ON discussions.user_id = users.id
            WHERE discussions.individual_id = ?
            ORDER BY is_sticky DESC, updated_at DESC, created_at DESC
        ";
        $discussions = $db->fetchAll($query, [$individual_id]);
        //Iterate through the discussions, and find comments
        foreach($discussions as $key=>$discussion) {
            $commentquery = "
                SELECT discussion_comments.*, users.first_name, users.last_name, users.avatar
                FROM discussion_comments 
                JOIN users ON discussion_comments.user_id = users.id
                WHERE discussion_id = ?
                ORDER BY created_at ASC
            ";
            $comments = $db->fetchAll($commentquery, [$discussion['id']]);
            $discussions[$key]['comments']=$comments;
        }
        
        return $discussions;
    }

    /**
     * Get the name of an individual given their ID.
     * 
     * @param int $individual_id The ID of the individual.
     * @return string The full name of the individual.
     */
    public static function getIndividualName($individual_id) {
        $sql="SELECT individuals.first_names, individuals.last_name FROM individuals WHERE individuals.id = ?"; 
        $db = Database::getInstance();
        $individual = $db->fetchOne($sql, [$individual_id]);
        return $individual['first_names']." ".$individual['last_name'];
    }
    
    /**
     * Get the next available item identifier for a new item.
     * 
     * @return int The next available item identifier.
     */
    public static function getNextItemIdentifier() {
        $sql = "SELECT COALESCE(MAX(item_identifier), 0) + 1 AS new_item_identifier FROM items";
        $db = Database::getInstance();
        $result = $db->fetchOne($sql);
        return $result['new_item_identifier'];        
    }

    /**
     * Returns a list of all items and their associated files and links.
     * 
     * @param int $individual_id The ID of the individual to filter by.
     * @return array An array of items with associated files and links.
     */
    public static function getAllFilesAndLinks($individual_id=null) {
        $sql = "
        SELECT items.*, item_links.item_id as item_link_item_id, item_links.individual_id as item_link_individual_id, fileConnections.*
        FROM `items` 
            INNER JOIN item_links ON items.item_id = item_links.item_id 
            LEFT JOIN ( 
                SELECT files.file_type, files.file_path, files.file_format, files.file_description, files.user_id as file_user_id, file_links.id as file_link_id, file_links.individual_id as file_link_individual_id, file_links.item_id as file_link_item_id 
                FROM file_links 
                INNER JOIN files ON files.id=file_links.file_id 
            ) as fileConnections ON fileConnections.file_link_item_id=items.item_id";
        if($individual_id) {
            $sql .= " WHERE item_links.individual_id = ?";
        }
        $sql .= " ORDER BY items.item_id ASC; ";
        $db = Database::getInstance();
        $itemlist = $db->fetchAll($sql);
        return $itemlist;        
    }

    /**
     * Returns a list of all items and their associated files and links.
     * 
     * 
     */
    public static function getItemTypes() {
        $response=array();
        // Marriage
        $response['Marriage'] = [
            'Spouse',
            'Date',
            'Location',
            'Reference',
            'Photo',
            'Story'
        ];

        // Divorce
        $response['Divorce'] = [
            'Spouse',
            'Date',
            'Reference',
            'Photo',
        ];

        // Birth - note that this entry automatically inserts the date from the individuals record
        $response['Birth'] = [
            'Location',
            'Reference',
            'Photo',
            'Story'
        ];

        // Baptism
        $response['Baptism'] = [
            'Date',
            'Location',
            'Reference',
            'Photo',
            'Story'
        ];        

        // Death - note that this entry automatically inserts the date from the individuals record
        $response['Death'] = [
            'Location',
            'Reference',
            'Photo',
            'Story'
        ];


        // Burial
        $response['Burial'] = [
            'Date',
            'Location',
            'Reference',
            'Photo',
            'Story'
        ];

        // Education
        $response['Education'] = [
            'Date',
            'Title',
            'Institution',
            'Reference',
            'Photo',
            'Story'
        ];

        // Military
        $response['Military'] = [
            'Started',
            'Ended',
            'Position',
            'Source',
            'Reference',
            'Photo',
            'Story'
        ];

        // Occupation
        $response['Occupation'] = [
            'Title',
            'Source',
            'Photo',
            'Story'
        ];

        // Residence
        $response['Residence'] = [
            'Date',
            'Location',
            'Source',
            'Photo',
            'Story'
        ];

        // migration
        $response['Migration'] = [
            'Departure',
            'Origin',
            'Arrival',
            'Destination',
            'Source',
            'Photo',
            'Story'
        ];


        /*$response['Key Image'] = [
            'Key Image'
        ];*/
        //Sort the responses alphabetically, but only by the key name
        //ksort($response);
        
        // Other
        $response['Other (group)'] = [
            'Date',
            'Title',
            'Source',
            'Photo',
            'Story'
        ];

        $response['Other (single)'] = [
            'free'
        ];


        return $response;
    }

    /**
     * Returns a list of all items and their associated files and links.
     * 
     * 
     */
    public static function getItemStyles() {
        $response=array();
        $response['Spouse']="individual"; 
        $response['Person']="individual"; 
        $response['Date']="date";
        $response['Started']="date";
        $response['Ended']="date";
        $response['Title']="text";
        $response['Location']="text";
        $response['Source']="text";
        $response['Position']="text";
        $response['Institution']="text";
        $response['free']="text";
        $response['Story']="textarea";
        $response['Certificate']="file";
        $response['Reference']="file";
        $response['Key_Image']="file";
        $response['Photo']="file";
        $response['File']="file";
        $response['Departure']="date";
        $response['Origin']="text";
        $response['Arrival']="date";
        $response['Destination']="text";
        return $response;
    }

    public static function getItemStyle($type) {
        $styles=self::getItemStyles();
        if(isset($styles[$type])) {
            return $styles[$type];
        } else {
            return "text";
        }
    }

    /**
     * Returns a list of changes since the last time the user was doing anything
     * 
     * This includes new discussions, new comments in the communications section
     * as well as changes to individuals in the family section - such as new individuals, new relationships, new items, new files, etc.
     *
     * @param int $user_id The ID of the user.
     * @param string $show_since The date to show changes since.
     */
    public static function getNewStuff($user_id, $show_since=null) {
        $response=array(
            'discussions'=>array(),
            'individuals'=>array(),
            'relationships'=>array(),
            'items'=>array(),
            'files'=>array()
        );
        // Get the database instance
        $db = Database::getInstance();
        if($show_since) {
            $last_active['last_view']=date('Y-m-d H:i:s', strtotime($show_since));
        } else {
            // Get the last time the user was active
            $sql = "SELECT COALESCE(last_view, last_login, registration_date) as last_view FROM users WHERE id = ?";
            $last_active = $db->fetchOne($sql, [$user_id]);
        }
        //If there is no last active time, set it to last week
        if(!$last_active['last_view']) {
            $last_active['last_view']=date('Y-m-d H:i:s', strtotime('-1 week'));
        }
        $response['last_view']=$last_active['last_view'];
        // Get all discussions that have been updated since the user was last active
        $sql = "SELECT discussions.title, discussions.content, discussions.id as discussionId, users.first_name as user_first_name, 
                users.last_name as user_last_name, users.avatar, individuals.first_names as tree_first_name, 
                discussions.individual_id, discussions.updated_at,
                individuals.last_name as tree_last_name, users.id as user_id, 'discussion' as change_type
                FROM discussions 
                JOIN users ON discussions.user_id = users.id
                LEFT JOIN individuals ON discussions.individual_id = individuals.id
                WHERE discussions.updated_at > ?
                ORDER BY updated_at DESC, created_at DESC";
        $discussions = $db->fetchAll($sql, [$last_active['last_view']]);
        //Iterate through the discussions, and find comments
        foreach($discussions as $discussion) {
            $response['discussions'][$discussion['discussionId']]=$discussion;
        }
        //Now find any comments that have been added since the u ser was last added, get their discussion_id
        // and add those discussions to the list
        $sql = "SELECT discussions.title, discussions.id as discussionId, users.first_name as user_first_name, 
                users.last_name as user_last_name, users.avatar, individuals.first_names as tree_first_name,
                discussions.individual_id, discussion_comments.comment as content, discussion_comments.updated_at,
                individuals.last_name as tree_last_name, users.id as user_id, 'comment' as change_type
                FROM discussion_comments
                JOIN discussions ON discussion_comments.discussion_id = discussions.id
                JOIN users ON discussion_comments.user_id=users.id
                LEFT JOIN individuals ON discussions.individual_id = individuals.id
                WHERE discussion_comments.created_at > ?
                ORDER BY discussion_comments.updated_at DESC, discussion_comments.created_at DESC";
        $comments = $db->fetchAll($sql, [$last_active['last_view']]);
        foreach($comments as $comment) {
            $response['discussions'][$comment['discussionId']]=$comment;
        }

        //$response['discussions']=$discussions;



        // Get all individuals that have been updated since the user was last active
        $sql = "SELECT individuals.id as individualId, 
                    users.first_name as user_first_name, users.last_name as user_last_name,
                    individuals.first_names as tree_first_name, individuals.last_name as tree_last_name,
                    individuals.aka_names, individuals.gender,
                    individuals.birth_year, individuals.death_year, individuals.created, individuals.updated,
                    COALESCE(
                        (SELECT files.file_path 
                            FROM file_links 
                            JOIN files ON file_links.file_id = files.id 
                            JOIN items ON items.item_id = file_links.item_id 
                            WHERE file_links.individual_id = individuals.id 
                            AND items.detail_type = 'Key Image'
                            LIMIT 1), 
                        '') AS keyimagepath
                FROM individuals
                LEFT JOIN users ON created_by = users.id
                WHERE created > ?
                ORDER BY updated DESC, created DESC";
        $individuals = $db->fetchAll($sql, [$last_active['last_view']]);
        $response['individuals']=$individuals;

        //Get all visitors to the site for the last 24 hours
        $sql = "SELECT users.first_name, users.last_name, users.id as user_id, 
                avatar, MAX(last_view) as last_view
                FROM users 
                WHERE last_view > ?
                AND show_presence = 1
                GROUP BY users.id
                ORDER BY last_view DESC";
        $visitors = $db->fetchAll($sql, [$last_active['last_view']]);
        $response['visitors']=$visitors;

        // Get all relationships that have been updated since the user was last active
        /* $sql = "SELECT subject_individual.first_names as subject_first_names, subject_individual.last_name as subject_last_name, 
                    subject_individual.id as subject_individualId, object_individual.first_names as object_first_names, 
                    object_individual.last_name as object_last_name, object_individual.id as object_individualId, 
                    relationships.relationship_type, relationships.updated 
            FROM relationships
            INNER JOIN individuals as subject_individual ON relationships.individual_id_1 = subject_individual.id
            INNER JOIN individuals as object_individual ON relationships.individual_id_2 = object_individual.id 
            WHERE relationships.updated > ?"; 
        $relationships = $db->fetchAll($sql, [$last_active['last_view']]);
        $response['relationships']=$relationships; */


        // Get all items that have been updated since the user was last active
        $items=Utils::getItems('%', $last_active['last_view'], "items.updated DESC");
        $response['items']=$items;


        // Get all files that have been updated since the user was last active (but only ones which aren't already connected to items)
        $sql = "SELECT files.*, file_links.item_id, users.first_name as user_first_name, users.last_name as user_last_name,
                individuals.id as individualId, individuals.first_names as tree_first_name, individuals.last_name as tree_last_name
                FROM files 
                JOIN file_links ON files.id = file_links.file_id 
                JOIN individuals ON file_links.individual_id = individuals.id
                LEFT JOIN users ON files.user_id = users.id
                WHERE files.upload_date > ? AND file_links.item_id IS NULL
                ORDER BY files.upload_date DESC";
        $files = $db->fetchAll($sql, [$last_active['last_view']]);
        $response['files']=$files;

        return $response;
        
    }

    public static function getMissingIndividualData($individual_id, $type="self", $relationshiplabel="") {
        $suggestions=[];
        //Get a list of missing information for an individual.
        $info=Utils::getIndividual($individual_id);
        $missingcoredata=[];
        
        $suggestions['details']=[
            'individual_id'=>$individual_id,
            'first_names'=>$info['first_names'],
            'last_name'=>$info['last_name'],
            'is_deceased'=>$info['is_deceased'],
            'relationshiplabel'=>$relationshiplabel
        ];

        foreach($info as $key=>$val) {
            if($val==null && $val !== 0) {
                if($key != "birth_prefix" && $key != "death_prefix" && $key != "aka_names") {
                    if($type=="self") {
                        if(substr($key, 0, 5) != "death") {
                            $missingcoredata[]=$key;
                        }
                    } elseif($type=="parents") {
                            //If the parent is marked as deceased and we don't have any death info:
                            if(substr($key, 0, 5) != "death") {
                                $missingcoredata[]=$key;
                            } else {
                                if($info['is_deceased'] == 1) {
                                    $missingcoredata[]=$key;
                                }
                            }
                    } else {
                        $missingcoredata[]=$key;
                    }
                }
            }
        }

        $suggestions['missingcoredata']=$missingcoredata;
        $completeditems=[];
        $items=Utils::getItems($individual_id);
        $missingitems=[];
        foreach($items as $item) {
            $completeditems[]=$item['item_group_name'];
        }
        $itemlist=["Key Image", "Birth", "Education", "Marriage", "Occupation", "Residence"];
        foreach($itemlist as $val) {
            if(!in_array($val, $completeditems)) {
                $missingitems[]=$val;
            }
        }

        $suggestions['missingitems']=$missingitems;

        return $suggestions;
    }

    public static function getMissingDataForUser($individual_id) {
        $missingdata=[];
        $missingdata['primary']['self'][$individual_id]=Utils::getMissingIndividualData($individual_id, "self", "Self");

        //Get parents
        $parents=Utils::getParents($individual_id);

        foreach($parents as $parent) {
            $relationship=Utils::getRelationshipLabel($individual_id, $parent['id']);
            $missingdata['parents'][$relationship][$parent['id']]=Utils::getMissingIndividualData($parent['id'], "parents", $relationship);
        }

        //Get grandparents
        foreach($parents as $parent) {
            $grandparents=Utils::getParents($parent['id']);
            foreach($grandparents as $grandparent) {
                $relationship=Utils::getRelationshipLabel($individual_id, $grandparent['id']);
                $missingdata['grandparents'][$relationship][$grandparent['id']]=Utils::getMissingIndividualData($grandparent['id'], "grandparents", $relationship);
            }
        }

        //Get siblings
        $siblings=Utils::getSiblings($individual_id);
        foreach($siblings as $sibling) {
            $relationship=Utils::getRelationshipLabel($individual_id, $sibling['id']);
            $missingdata['siblings'][$relationship][$sibling['id']]=Utils::getMissingIndividualData($sibling['id'], "self", $relationship);
        }

        //echo "<pre>"; print_r($missingdata); echo "</pre>";
        return($missingdata);
    }
}
