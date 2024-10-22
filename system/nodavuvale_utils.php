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
        function addIndividualToTree($id, $relationshipLookup, $individualLookup, &$processedIds, $generation, &$treeData) {
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
            $lifeSpan = "$birthYear - $deathYear";
            $gender = !empty($individual['gender']) ? $individual['gender'] : 'other';
            $keyImage = !empty($individual['keyimagepath']) ? $individual['keyimagepath'] : 'images/default_avatar.webp';

            $parents = findParents($id, $relationshipLookup, $individualLookup);

            //Check if parents are already in the treeData
            $parentsInTree=false;
            foreach($parents as $parent) {
                foreach($treeData as $node) {
                    if($node['id']==$parent['id']) {
                        $parentsInTree=true;
                        break 2;
                    }
                }
            }

            $parentLink="";
            if((!$parentsInTree || $generation==1) && isset($parents) && count($parents) > 0) {
                $parentLinkId=$parents[0]['id'];
                $parentLink = '<div class="parents-link absolute right-0 top-0 mr-1 mt-1 text-burnt-orange text-xs" title="View parents" onClick="window.location.href=\'?to=family/tree&root_id=' . $parentLinkId .'\'"><i class="fas fa-level-up-alt"></i></div>';
            }

            $nodeBodyTemplate = "
            <input type='hidden' class='individualid' value='{individualId}'>
            <input type='hidden' class='individualgender' value='{individualGender}'>
            <div class='nodeBodyText'>
                {parentLink}
                <img src='{individualKeyImage}' class='nodeImage border object-cover' title='{individualFullName}'>
                <span class='bodyName' title='See details for {individualFullName}' onclick='window.location.href=&apos;?to=family/individual&amp;individual_id={individualId}&apos;'>
                    {individualPrefName}<br>
                    {individualLastName}
                </span>
                <span style='font-size: 0.7rem'>
                    {individualLifeSpan}
                </span>
            </div>
            <button class='text-sm md:text-md ft-view-btn float-right' title='Start tree at this individual' onclick='window.location.href=&apos;?to=family/tree&amp;root_id={individualId}&apos;'>
                ➜
            </button>
            <span class='float-left inline'>&nbsp;</span>
            <button class='text-sm md:text-md ft-edit-btn float-left' title='Edit this individual' onclick='editIndividualFromTreeNode(&apos;{individualId}&apos;)'>
                📝
            </button>
            <span class='inline float-right'>&nbsp;</span>
            <button class='text-sm md:text-md ft-dropdown-btn ' title='Add a relationship to this individual' onclick='addRelationshipToIndividualFromTreeNode(this)'>
                🔗
            </button>";
            
            // Replace the template placeholders with actual values
            $nodeBodyText = str_replace(
                ['{parentLink}', '{individualId}', '{individualGender}', '{individualKeyImage}', '{individualFullName}', '{individualPrefName}', '{individualLastName}', '{individualLifeSpan}'],
                [$parentLink, $individual['id'], $gender, $keyImage, $fullName, $prefName, $individual['last_name'], $lifeSpan],
                $nodeBodyTemplate
            );

            $node = [
                'id' => $individual['id'],
                'name' => $nodeBodyText,
                'class' => 'node treegender_'.$gender. ' generation_'.$generation,
                'depthOffset' => 0
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
        function createMarriageGroup($id, $relationshipLookup, $individualLookup, &$processedIds, $generation, &$treeData) {
            // Add the individual to the tree
            $individualNode = addIndividualToTree($id, $relationshipLookup, $individualLookup, $processedIds, $generation, $treeData);
            if (!$individualNode) return null;

            $marriages = [];
            $childrenWithBothParents = [];
            $childrenWithSingleParent = [];
            $existingSpouses = [];

            // Step 1: Find explicit spouses of the individual
            $explicitSpouses = findExplicitSpouses($id, $relationshipLookup);

            // Add all explicit spouses to the marriages list
            foreach ($explicitSpouses as $spouseId) {
                $spouseNode = addIndividualToTree($spouseId, $relationshipLookup, $individualLookup, $processedIds, $generation, $treeData);
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
                        $childNode = createMarriageGroup($childId, $relationshipLookup, $individualLookup, $processedIds, $generation + 1, $treeData);
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
                                    $spouseNode = addIndividualToTree($otherParent, $relationshipLookup, $individualLookup, $processedIds, $generation, $treeData);
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
        $treeData[] = createMarriageGroup($rootId, $relationshipLookup, $individualLookup, $processedIds, 1, $treeData);

        return json_encode($treeData);
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
                    '') AS keyimagepath
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
                    '') AS keyimagepath
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
                individual_spouse.*, files.file_path as keyimagepath
            FROM
                relationships AS r
            INNER JOIN individuals AS individual_spouse ON 
                (CASE 
                    WHEN r.individual_id_1 = ? THEN r.individual_id_2 
                    ELSE r.individual_id_1 
                END) = individual_spouse.id
            LEFT JOIN file_links ON file_links.individual_id=individual_spouse.id 
            LEFT JOIN files ON file_links.file_id=files.id     
            LEFT JOIN items ON items.item_id=file_links.item_id AND items.detail_type='Key Image'
                            
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
            SELECT distinct individuals.*, files.file_path as keyimagepath 
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
                SELECT distinct individuals.*, files.file_path as keyimagepath
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
                    '') AS keyimagepath
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

    public static function getItems($individual_id) {
        // Get the database instance
        $db = Database::getInstance();
        // Fetch items using the updated query
        $query = "
            SELECT items.*, item_groups.item_group_name, files.id as file_id, files.*, users.first_name, users.last_name
            FROM items 
            INNER JOIN item_links ON items.item_id=item_links.item_id
            LEFT JOIN file_links ON items.item_id = file_links.item_id
            LEFT JOIN files ON file_links.file_id = files.id
            LEFT JOIN item_groups ON items.item_identifier = item_groups.item_identifier
            LEFT JOIN users ON items.user_id = users.id
            WHERE item_links.individual_id = ?
            ORDER BY item_identifier ASC, items.updated DESC 
        ";
        $items = $db->fetchAll($query, [$individual_id]);

        // Group items by item_identifier - if there is none, treat as individual groups
        $groupedItems = [];
        foreach ($items as $item) {
            $itemIdentifier = $item['item_group_name'];
            if (empty($itemIdentifier)) {
                $itemIdentifier = "Singleton";
            }
            if (!isset($groupedItems[$itemIdentifier])) {
                $groupedItems[$itemIdentifier] = []; //Create empty array for new item group
            }
            $groupedItems[$itemIdentifier][] = $item;
        }

        
        return $groupedItems;
    }

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
                SELECT *
                FROM discussion_comments 
                WHERE discussion_id = ?
                ORDER BY created_at ASC
            ";
            $comments = $db->fetchAll($commentquery, [$discussion['id']]);
            $discussions[$key]['comments']=$comments;
        }
        
        return $discussions;
    }

    public static function getIndividualName($individual_id) {
        $sql="SELECT individuals.first_names, individuals.last_name FROM individuals WHERE individuals.id = ?"; 
        $db = Database::getInstance();
        $individual = $db->fetchOne($sql, [$individual_id]);
        return $individual['first_names']." ".$individual['last_name'];
    }

    public static function getItemTypes() {
        $response=array();
        // Marriage
        $response['Marriage'] = [
            'Date',
            'Location',
            'Certificate',
            'Photo',
            'Story'
        ];

        // Birth
        $response['Birth'] = [
            'Location',
            'Certificate',
            'Photo',
            'Story'
        ];

        // Death
        $response['Death'] = [
            'Location',
            'Certificate',
            'Photo',
            'Story'
        ];

        // Baptism
        $response['Baptism'] = [
            'Date',
            'Location',
            'Certificate',
            'Photo',
            'Story'
        ];

        // Burial
        $response['Burial'] = [
            'Date',
            'Location',
            'Certificate',
            'Photo',
            'Story'
        ];

        // Education
        $response['Education'] = [
            'Date',
            'Title',
            'Institution',
            'Certificate',
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

        // Military
        $response['Military'] = [
            'Date',
            'Position',
            'Source',
            'Photo',
            'Story'
        ];



        $response['Key Image'] = [
            'Key Image'
        ];
        //Sort the responses alphabetically
        ksort($response);      
        
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

    public static function getItemStyles() {
        $response=array();
        $response['Date']="date";
        $response['Location']="text";
        $response['Certificate']="file";
        $response['Photo']="file";
        $response['Story']="textarea";
        $response['Title']="text";
        $response['Institution']="text";
        $response['Source']="text";
        $response['Position']="text";
        $response['Key_Image']="file";
        $response['free']="text";
        return $response;
    }
}
