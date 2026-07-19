<?php

namespace App\Console\Commands;

use App\Mail\LicenseExpiringMail;
use App\Models\GlobalSetting;
use App\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa por correo a las organizaciones cuya licencia está por vencer.
 * Por defecto notifica a 5 y a 1 día del vencimiento.
 * Programado en routes/console.php para correr una vez al día.
 */
class NotifyExpiringLicenses extends Command
{
    protected $signature = 'licenses:notify-expiring {--days=5,1 : Días de antelación, separados por coma}';

    protected $description = 'Envía avisos de vencimiento de licencia a las organizaciones.';

    public function handle(): int
    {
        $thresholds = collect(explode(',', $this->option('days')))
            ->map(fn ($d) => (int) trim($d))
            ->filter()
            ->all();

        $paymentInfo = [
            'account' => GlobalSetting::where('key', 'payment_account')->value('value') ?? '',
            'whatsapp' => GlobalSetting::where('key', 'payment_whatsapp')->value('value') ?? '',
        ];

        $sent = 0;
        $summary = [];

        foreach ($thresholds as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            $organizations = Organization::where('is_lifetime', false)
                ->where('status', 'active')
                ->whereNotNull('license_expires_at')
                ->whereDate('license_expires_at', $targetDate)
                ->with('user')
                ->get();

            foreach ($organizations as $org) {
                $summary[] = ['name' => $org->name, 'days' => $days, 'date' => $org->license_expires_at->format('d/m/Y')];

                $email = $org->user?->email ?? $org->email;
                if (!$email) {
                    continue;
                }

                Mail::to($email)->send(new LicenseExpiringMail(
                    name: $org->user?->name ?? $org->name,
                    daysLeft: $days,
                    expiresAt: $org->license_expires_at->format('d/m/Y'),
                    paymentInfo: $paymentInfo
                ));
                $sent++;
            }
        }

        // Resumen interno para el equipo root.
        if (!empty($summary)) {
            $rows = collect($summary)
                ->map(fn ($s) => '<li><strong>' . e($s['name']) . '</strong> — vence en ' . $s['days'] . ' día(s) (' . e($s['date']) . ')</li>')
                ->implode('');
            \App\Services\AdminNotifier::notifyRoot(
                'expiring',
                '⏳ Licencias por vencer: ' . count($summary),
                '<h2>Licencias próximas a vencer</h2><ul>' . $rows . '</ul>'
            );
        }

        $this->info("Avisos de vencimiento enviados: {$sent}");

        return Command::SUCCESS;
    }
}
