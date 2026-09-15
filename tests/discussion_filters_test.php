<?php
require_once dirname(__DIR__) . '/system/DiscussionFilters.php';
function filterAssert(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
$rows = [
    ['id'=>3,'title'=>'Family reunion','content'=>'<p>Gathering in Suva</p>','is_event'=>1],
    ['id'=>1,'title'=>'Voyage','content'=>'<p>Old <strong>family</strong> history &amp; memories</p>','is_historical_event'=>1],
    ['id'=>2,'title'=>'Village update','content'=>'<p>Good news</p>','is_news'=>1],
    ['id'=>4,'title'=>'Other story','content'=>'<p>A café</p><p>by the sea</p>'],
    ['id'=>5,'title'=>'Shared celebration','content'=>'<img alt="not searchable" src="hidden.png">','is_news'=>1,'is_event'=>1],
];
$ids = static fn(array $query) => array_column((new DiscussionFilters($query))->apply($rows), 'id');
filterAssert($ids([]) === [3,1,2,4,5], 'Defaults retain every category and original pinned/date ordering');
filterAssert($ids(['filter'=>'1']) === [], 'All categories unchecked means no results');
filterAssert($ids(['categories'=>['other']]) === [4], 'Other excludes every classified post');
filterAssert($ids(['categories'=>['family']]) === [3,5], 'Family events include multi-category posts');
filterAssert($ids(['categories'=>['historical','news']]) === [1,2,5], 'Selected categories combine with OR');
filterAssert($ids(['q'=>'FAMILY HISTORY & memories']) === [1], 'Search is case insensitive and ignores inline HTML');
filterAssert($ids(['q'=>'CAFÉ by the sea']) === [4], 'Unicode search works across paragraphs');
filterAssert($ids(['q'=>'family','categories'=>['historical']]) === [1], 'Search and category filters combine');
filterAssert($ids(['q'=>'hidden.png']) === [], 'Search excludes HTML attributes');
filterAssert($ids(['categories'=>['unknown', ['news']]]) === [], 'Unknown or nested categories are ignored');
filterAssert($ids(['q'=>['bad']]) === [3,1,2,4,5], 'Malformed search input is handled safely');
filterAssert(mb_strlen((new DiscussionFilters(['q'=>str_repeat('x', 201)]))->search) === 200, 'Search length bounded');
echo "Discussion search and category filter tests passed.\n";
