<?php
// Run inside the disposable database and HTTP integration harness.
if (!isset($pdo, $http, $memberCookie)) { exit("Run tests/public_access_integration_test.php.\n"); }
$fixture = $pdo->prepare('INSERT INTO discussions (user_id,title,content,is_event,is_historical_event,is_news,individual_id,created_at)
    VALUES (2,?,?,?,?,?,?,NOW())');
$filterIds = [];
foreach (['family'=>[1,0,0,0], 'historical'=>[0,1,0,0], 'news'=>[0,0,1,0], 'other'=>[0,0,0,0],
    'mixed'=>[1,0,1,0], 'individual'=>[0,0,0,1]] as $category => $flags) {
    $fixture->execute(array_merge(['Filter fixture ' . $category, '<p>Searchable <strong>family</strong> history &amp; memories</p>'], $flags));
    $filterIds[$category] = (int) $pdo->lastInsertId();
}
$filterPath = '/index.php?to=communications/discussions&';
foreach ([['family'], ['historical'], ['news'], ['other'], ['historical','news'], []] as $categories) {
    $response = $http($filterPath . http_build_query(['filter'=>'1','q'=>'Filter fixture','categories'=>$categories]), $memberCookie);
    integrationAssert($response['status'] === 200, 'Filtered discussion page renders');
    foreach ($filterIds as $category => $id) {
        $expected = $category !== 'individual' && (in_array($category, $categories, true)
            || ($category === 'mixed' && (in_array('family', $categories, true) || in_array('news', $categories, true))));
        integrationAssert(str_contains($response['body'], 'id="discussion_id_' . $id . '"') === $expected, 'Category filter includes only matching general discussion: ' . $category);
    }
    if (!$categories) { integrationAssert(str_contains($response['body'], 'No discussions match your search and filters.'), 'Empty selection has a helpful empty state'); }
}
$response = $http($filterPath . http_build_query(['filter'=>'1','q'=>'FAMILY HISTORY & memories','categories'=>['historical']]), $memberCookie);
integrationAssert(str_contains($response['body'], 'id="discussion_id_' . $filterIds['historical'] . '"')
    && !str_contains($response['body'], 'id="discussion_id_' . $filterIds['family'] . '"'), 'Text and category filtering work together over HTTP');
integrationAssert(str_contains($response['body'], 'value="FAMILY HISTORY &amp; memories"')
    && preg_match('/value="historical"\s+checked/', $response['body'])
    && !preg_match('/name="categories\[\]" value="family"\s+checked/', $response['body']), 'Search and selected categories persist in the form');
$response = $http($filterPath . http_build_query(['q'=>'Filter fixture']), $memberCookie);
integrationAssert(str_contains($response['body'], 'Showing 5 of'), 'Search without category filters defaults to all categories');
integrationAssert(strpos($response['body'], 'id="discussion-search"') < strpos($response['body'], 'id="newDiscussionForm"'), 'Search is above the composer and discussion list');
$response = $http($filterPath . http_build_query(['q'=>'"><script>search-marker</script>']), $memberCookie);
integrationAssert(str_contains($response['body'], '&quot;&gt;&lt;script&gt;search-marker&lt;/script&gt;'), 'Search text is HTML escaped');
integrationAssert($http($filterPath . 'q=Filter')['status'] === 302, 'Filtering does not bypass member access');
echo "Discussion search and filtering HTTP tests passed.\n";
