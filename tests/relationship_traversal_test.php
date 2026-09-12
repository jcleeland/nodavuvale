<?php

require_once dirname(__DIR__) . '/system/nodavuvale_utils.php';

function relationshipTraversalAssertSame(string $expected, string $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . " Expected '{$expected}', received '{$actual}'.");
    }
}

relationshipTraversalAssertSame(
    'parent',
    Utils::getRelationshipTraversalDirection('child', true),
    'individual_id_1 must be described as parent of individual_id_2.'
);
relationshipTraversalAssertSame(
    'child',
    Utils::getRelationshipTraversalDirection('child', false),
    'individual_id_2 must be described as child of individual_id_1.'
);
relationshipTraversalAssertSame(
    'spouse',
    Utils::getRelationshipTraversalDirection('spouse', true),
    'Spouse traversal must be symmetric.'
);
relationshipTraversalAssertSame(
    'spouse',
    Utils::getRelationshipTraversalDirection('partner', false),
    'Partner traversal must be symmetric.'
);
relationshipTraversalAssertSame(
    'related',
    Utils::getRelationshipTraversalDirection('unknown', true),
    'Unknown relationship types must use the neutral fallback.'
);

echo "Relationship traversal direction tests passed.\n";
