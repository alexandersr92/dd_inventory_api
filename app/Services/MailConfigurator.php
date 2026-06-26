<?php

namespace App\Services;

use App\Models\SystemConfig;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use App\Mail\DynamicSystemMail;

class MailConfigurator
{
    /**
     * Get a system configuration value, decrypting SMTP password if requested.
     */
    public static function get(string $key, $default = null)
    {
        $config = SystemConfig::where('key', $key)->first();
        if (!$config) {
            return $default;
        }

        if ($key === 'smtp_password' && !empty($config->value)) {
            try {
                return Crypt::decryptString($config->value);
            } catch (\Exception $e) {
                return '';
            }
        }

        return $config->value;
    }

    /**
     * Save a system configuration value, encrypting SMTP password if needed.
     */
    public static function set(string $key, ?string $value): void
    {
        if ($key === 'smtp_password' && !empty($value)) {
            $value = Crypt::encryptString($value);
        }

        SystemConfig::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Dynamically reconfigure Laravel's mail settings based on stored database configurations.
     */
    public static function applyConfiguration(): bool
    {
        $mailer = self::get('smtp_mailer', 'smtp');
        
        // If mailer is set to 'log', we just let it use the default log driver.
        if ($mailer === 'log') {
            config(['mail.default' => 'log']);
            Mail::purge();
            return true;
        }

        $host = self::get('smtp_host');
        $port = self::get('smtp_port');
        $username = self::get('smtp_username');
        $password = self::get('smtp_password');
        $encryption = self::get('smtp_encryption');
        $fromAddress = self::get('smtp_from_address', 'noreply@example.com');
        $fromName = self::get('smtp_from_name', 'System');

        if (empty($host) || empty($port)) {
            return false;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int)$port,
            'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
            'mail.mailers.smtp.username' => $username,
            'mail.mailers.smtp.password' => $password,
            'mail.from.address' => $fromAddress,
            'mail.from.name' => $fromName,
        ]);

        // Purge Laravel's cached mailer instance so that it picks up the new config values
        Mail::purge('smtp');
        
        return true;
    }

    /**
     * Send an email dynamically using an EmailTemplate key and variable replacements.
     */
    public static function send(string $to, string $templateKey, array $data = []): void
    {
        // 1. Apply active configurations
        self::applyConfiguration();

        // 2. Load the template
        $template = EmailTemplate::where('key', $templateKey)->first();
        if (!$template) {
            throw new \Exception("La plantilla de correo '{$templateKey}' no existe.");
        }

        // 3. Perform variable replacements
        $subject = $template->subject;
        $body = $template->body;

        foreach ($data as $key => $val) {
            $placeholder = '{' . $key . '}';
            $subject = str_replace($placeholder, $val, $subject);
            $body = str_replace($placeholder, $val, $body);
        }

        // 4. Dispatch the mail
        Mail::to($to)->send(new DynamicSystemMail($subject, $body));
    }
}
