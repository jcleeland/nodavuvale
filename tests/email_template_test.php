<?php

require_once dirname(__DIR__) . '/system/EmailTemplate.php';

function emailTemplateAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$settings = ['site_name' => 'Family & Friends'];
$rendered = EmailTemplate::render($settings, 'login_details', [
    'first_name' => '<Jason>',
    'last_name' => 'Example',
    'email' => 'jason+family@example.test',
    'site_name' => 'Family & Friends',
    'login_url' => 'https://example.test/?to=login&from=email',
]);

emailTemplateAssert(
    $rendered['subject'] === 'Welcome to Family & Friends. This is your account information.',
    'Default subject tokens are rendered as plain text.'
);
emailTemplateAssert(
    str_contains($rendered['body'], 'Hi &lt;Jason&gt;,')
        && str_contains($rendered['body'], 'Family &amp; Friends')
        && str_contains($rendered['body'], 'https://example.test/?to=login&amp;from=email'),
    'Values inserted into the HTML body are escaped.'
);

$overrides = [
    'email_template_password_reset_subject' => 'Password for {{first_name}}',
    'email_template_password_reset_body' => '<p>{{first_name}}: {{password}} at {{login_url}}</p>',
];
$rendered = EmailTemplate::render($overrides, 'password_reset', [
    'first_name' => 'A & B',
    'password' => '<temporary>',
    'login_url' => 'https://example.test/login?a=1&b=2',
]);

emailTemplateAssert($rendered['subject'] === 'Password for A & B', 'Saved subject overrides are used.');
emailTemplateAssert(
    $rendered['body'] === '<p>A &amp; B: &lt;temporary&gt; at https://example.test/login?a=1&amp;b=2</p>',
    'Saved body overrides are used and rendered safely.'
);

$unknownRejected = false;
try {
    EmailTemplate::template([], 'not_a_template');
} catch (InvalidArgumentException $error) {
    $unknownRejected = true;
}
emailTemplateAssert($unknownRejected, 'Unknown template names are rejected.');

$allValues = [
    'first_name' => 'First',
    'last_name' => 'Last',
    'email' => 'person@example.test',
    'password' => 'temporary-password',
    'site_name' => 'Family Site',
    'login_url' => 'https://example.test/login',
];
foreach (array_keys(EmailTemplate::definitions()) as $templateName) {
    $rendered = EmailTemplate::render([], $templateName, $allValues);
    emailTemplateAssert(
        !str_contains($rendered['subject'], '{{') && !str_contains($rendered['body'], '{{'),
        'Every default ' . $templateName . ' placeholder can be rendered.'
    );
}

echo "Email template tests passed.\n";
