<?php
require_once dirname(__DIR__) . '/system/PublicAccess.php';
require_once dirname(__DIR__) . '/system/RequestSecurity.php';
require_once dirname(__DIR__) . '/views/public/format.php';

function publicAssert(bool $value, string $message): void
{
    if (!$value) { throw new RuntimeException($message); }
}
$today = new DateTimeImmutable('2026-09-14', new DateTimeZone('Australia/Sydney'));
$base = ['exclude_from_public' => 0, 'death_prefix' => '', 'death_year' => 1976, 'death_month' => 9, 'death_date' => 13];
$cases = [
    ['One day over the threshold', [], true],
    ['Exactly fifty years', ['death_date' => 14], false],
    ['Less than fifty years', ['death_date' => 15], false],
    ['Explicit exclusion wins', ['exclude_from_public' => 1, 'death_year' => 1800], false],
    ['Unknown death', ['death_year' => null, 'is_deceased' => 1], false],
    ['Year only old enough', ['death_year' => 1975, 'death_month' => null, 'death_date' => null], true],
    ['Year only too recent', ['death_month' => null, 'death_date' => null], false],
    ['Month only old enough', ['death_month' => 8, 'death_date' => null], true],
    ['Month only too recent', ['death_date' => null], false],
    ['Before uses conservative upper bound', ['death_prefix' => 'before', 'death_year' => 1900], true],
    ['About remains uncertain', ['death_prefix' => 'about', 'death_year' => 1800], false],
    ['After remains uncertain', ['death_prefix' => 'after', 'death_year' => 1800], false],
    ['Unknown qualifier', ['death_prefix' => 'probably', 'death_year' => 1800], false],
    ['Bad month', ['death_month' => 13], false],
    ['Bad day', ['death_month' => 2, 'death_date' => 30], false],
    ['Day without month', ['death_month' => null], false],
    ['Invalid year', ['death_year' => '1870oops'], false],
    ['Zero date parts', ['death_year' => 1900, 'death_month' => 0, 'death_date' => 0], true],
    ['Far-future year does not overflow public date', ['death_year' => 9949, 'death_month' => 12, 'death_date' => 31], false],
];
foreach ($cases as [$label, $changes, $expected]) {
    publicAssert(PublicAccess::eligible(array_replace($base, $changes), $today) === $expected, $label);
}
$missing = $base;
unset($missing['exclude_from_public']);
publicAssert(!PublicAccess::eligible($missing, $today), 'Missing schema field must fail closed');
$leap = array_replace($base, ['death_year' => 1976, 'death_month' => 2, 'death_date' => 29]);
publicAssert(!PublicAccess::eligible($leap, new DateTimeImmutable('2026-02-28')), 'Clamped leap anniversary is not over fifty');
publicAssert(PublicAccess::eligible($leap, new DateTimeImmutable('2026-03-01')), 'Day after leap anniversary qualifies');
publicAssert(!PublicAccess::eligible($base, $today, 51), 'Configurable threshold applies');
publicAssert(!PublicAccess::validSettings(['enabled' => 1, 'threshold_years' => 0, 'timezone' => 'UTC']), 'Reject zero threshold');
publicAssert(!PublicAccess::validSettings(['enabled' => 1, 'threshold_years' => 50, 'timezone' => 'invalid']), 'Reject timezone');
publicAssert(PublicAccess::validSettings(['enabled' => 0, 'threshold_years' => 50, 'timezone' => 'Australia/Sydney']), 'Accept defaults');
$_SESSION = [];
publicAssert(!RequestSecurity::validToken(''), 'Missing session token rejected');
$token = RequestSecurity::token();
publicAssert(RequestSecurity::validToken($token), 'Correct CSRF token accepted');
publicAssert(!RequestSecurity::validToken('wrong') && !RequestSecurity::validToken([$token]), 'Forged or malformed token rejected');
publicAssert(ancestorEscape('<script>') === '&lt;script&gt;', 'Public text escaped');

// Exercise the real AJAX handler: protected fields must never reach the DB.
$db = new class {
    public int $calls = 0;
    public function update($sql, $values) { ++$this->calls; return true; }
};
$data = ['individual_id' => '1', 'exclude_from_public' => '0'];
require dirname(__DIR__) . '/system/ajax/update_individual.php';
publicAssert($db->calls === 0 && $response['status'] === 'error', 'AJAX cannot change exclusions');
$data = ['individual_id' => '1', 'first_names' => 'Updated'];
require dirname(__DIR__) . '/system/ajax/update_individual.php';
publicAssert($db->calls === 1 && $response['status'] === 'success', 'Ordinary AJAX editing retained');
echo "Public access tests passed (date policy, CSRF, escaping, AJAX field protection).\n";
