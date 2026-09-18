<?php

/**
 * Provides editable templates for the automatic emails sent to users.
 *
 * Template overrides are stored in site_settings. The defaults intentionally
 * preserve the messages that were previously embedded in the email handlers.
 */
final class EmailTemplate
{
    public static function definitions(): array
    {
        return [
            'login_details' => [
                'label' => 'Login details',
                'description' => 'Sent by the “Send Login Details” action. It assumes the user already knows the password they created.',
                'subject_setting' => 'email_template_login_details_subject',
                'body_setting' => 'email_template_login_details_body',
                'subject' => 'Welcome to {{site_name}}. This is your account information.',
                'body' => <<<'HTML'
Hi {{first_name}},
<p>Welcome! Your account on the <a href="{{login_url}}">{{site_name}}</a> website has been activated.</p>
<p>This is a site which has been set up for descendants of the family of Soli Losana Nataleira to record and view their family history.</p>
<p>It also aims to keep us in touch with each other, with family in Nataleira and Navasau, and to learn more about each other across our full family diaspora.</p>
<p>The site has been developed and is managed by me, <a href="mailto:jason@cleeland.org">Jason Cleeland</a>, <i>(Soli-&gt;Mary Macdonald-&gt;William Macdonald-&gt;Alexander Macdonald-&gt;Jan Macdonald-&gt;Jason)</i>.</p>
<p>It is hosted on my local home server in Melbourne, Australia. It's not as fast as it will eventually be when I move it to a more permanent web location.</p>
<p>It's a work in progress, so please be patient and make sure you let me know of anything that you want or which doesn't work the way you'd like it to.</p>
<hr>
<p><b>Your {{site_name}} Account</b></p>
<p>Login using the following details:</p>
<p><b>Email/Login:</b> {{email}}</p>
<p><b>Password:</b> The password you created when you registered.</p>
<p>Login here: {{login_url}}</p>
<hr>
<p><i>Note: If you do not know what this is and didn't want an account on <i>{{site_name}}</i>, please contact the site administrator.</i></p>
<p>Lolomas,</p>
<b><i>Jason ({{site_name}} Admin)</i></b><br>
jason@cleeland.org
HTML,
                'tokens' => ['first_name', 'last_name', 'email', 'site_name', 'login_url'],
            ],
            'password_reset' => [
                'label' => 'Password reset',
                'description' => 'Sent by “Reset Password” and by the public forgotten-password form.',
                'subject_setting' => 'email_template_password_reset_subject',
                'body_setting' => 'email_template_password_reset_body',
                'subject' => '{{site_name}} - Password Reset',
                'body' => <<<'HTML'
Hi {{first_name}},
<p>Your {{site_name}} password has been reset.</p>
<p>Your new password is: {{password}}</p>
<p>Login here: {{login_url}}</p>
<p><i>Note: If you did not ask for your password to be reset, please contact the site administrator.</i></p>
<p>Lolomas,</p>
<b><i>{{site_name}} Admin</i></b>
HTML,
                'tokens' => ['first_name', 'last_name', 'email', 'password', 'site_name', 'login_url'],
            ],
            'new_account' => [
                'label' => 'New account with generated password',
                'description' => 'Used when a new random password is generated as part of a welcome email.',
                'subject_setting' => 'email_template_new_account_subject',
                'body_setting' => 'email_template_new_account_body',
                'subject' => 'Welcome to {{site_name}}. This is your account information.',
                'body' => <<<'HTML'
Hi {{first_name}},
<p>Welcome! You have an account on the <a href="{{login_url}}">{{site_name}}</a> website.</p>
<p>This is a site which has been set up for descendants of the family of Soli Losana Nataleira to record and view their family history.</p>
<p>It also aims to keep us in touch with each other, with family in Nataleira and Navasau, and to learn more about each other across our full family diaspora.</p>
<p>The site has been developed and is managed by me, Jason Cleeland, <i>(Soli-&gt;Mary Macdonald-&gt;William Macdonald-&gt;Alexander Macdonald-&gt;Jan Macdonald-&gt;Jason)</i>.</p>
<p>It is hosted on my local home server in Melbourne, Australia. It's not as fast as it will eventually be when I move it to a more permanent web location.</p>
<p>It's a work in progress, so please be patient and make sure you let me know of anything that you want or which doesn't work the way you'd like it to.</p>
<hr>
<p><b>Your {{site_name}} Account</b></p>
<p>Login using the following details:</p>
<p><b>Email/Login:</b> {{email}}</p>
<p><b>Password:</b> {{password}}<br><i>(Note this is a <b>new</b> randomly generated password. You can change this once you have logged in, on your account page)</i></p>
<p>Login here: {{login_url}}</p>
<hr>
<p><i>Note: If you do not know what this is and didn't want an account on <i>{{site_name}}</i>, please contact the site administrator.</i></p>
<p>Lolomas,</p>
<b><i>Jason ({{site_name}} Admin)</i></b><br>
jason@cleeland.org
HTML,
                'tokens' => ['first_name', 'last_name', 'email', 'password', 'site_name', 'login_url'],
            ],
        ];
    }

    public static function template(array $siteSettings, string $name): array
    {
        $definitions = self::definitions();
        if (!isset($definitions[$name])) {
            throw new InvalidArgumentException('Unknown email template.');
        }

        $definition = $definitions[$name];
        return [
            'subject' => array_key_exists($definition['subject_setting'], $siteSettings)
                ? $siteSettings[$definition['subject_setting']]
                : $definition['subject'],
            'body' => array_key_exists($definition['body_setting'], $siteSettings)
                ? $siteSettings[$definition['body_setting']]
                : $definition['body'],
        ];
    }

    public static function render(array $siteSettings, string $name, array $values): array
    {
        $template = self::template($siteSettings, $name);
        $subjectValues = [];
        $bodyValues = [];

        foreach ($values as $token => $value) {
            $placeholder = '{{' . $token . '}}';
            $plainValue = (string) $value;
            $subjectValues[$placeholder] = $plainValue;
            $bodyValues[$placeholder] = htmlspecialchars($plainValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return [
            'subject' => strtr($template['subject'], $subjectValues),
            'body' => strtr($template['body'], $bodyValues),
        ];
    }
}
