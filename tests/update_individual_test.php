<?php

function captureIndividualUpdate(array $dateFields): array
{
    $_POST = array_merge([
        'individual_id' => '42',
        'first_names' => 'Test',
        'aka_names' => '',
        'last_name' => 'Person',
        'gender' => 'other',
    ], $dateFields);
    $db = new class {
        public array $params = [];

        public function query($sql, $params = [])
        {
            $this->params = $params;
            return true;
        }
    };

    require dirname(__DIR__) . '/views/family/helpers/update_individual.php';

    return array_slice($db->params, 3, 8);
}

$populatedDates = [
    'birth_prefix' => 'about',
    'birth_year' => '1980',
    'birth_month' => '5',
    'birth_date' => '12',
    'death_prefix' => 'before',
    'death_year' => '2020',
    'death_month' => '6',
    'death_date' => '23',
];
$originalPost = $_POST;

try {
    $blankDates = array_fill_keys(array_keys($populatedDates), '');
    if (captureIndividualUpdate($blankDates) !== array_fill(0, 8, null)) {
        throw new RuntimeException('Blank date fields must be passed to the database as NULL.');
    }

    if (captureIndividualUpdate($populatedDates) !== array_values($populatedDates)) {
        throw new RuntimeException('Populated date fields must retain their submitted values.');
    }

    $blankBirthPrefix = $populatedDates;
    $blankBirthPrefix['birth_prefix'] = '';
    $expected = array_values($populatedDates);
    $expected[0] = null;
    if (captureIndividualUpdate($blankBirthPrefix) !== $expected) {
        throw new RuntimeException('A blank birth prefix must become NULL without changing populated dates.');
    }
} finally {
    $_POST = $originalPost;
}

echo "Individual update date tests passed.\n";
