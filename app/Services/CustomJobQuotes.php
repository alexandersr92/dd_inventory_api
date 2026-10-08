<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CustomJobQuote;
use App\Models\Invoice;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class CustomJobQuotes
{
    public function __construct(private CustomJobCalculationContext $context, private CustomJobCalculator $calculator) {}

    public function accessible(User $user, string $id, bool $lock = false): CustomJobQuote
    {
        $row = CustomJobQuote::query()->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
        $this->context->store($user, $row->store_id);

        return $row;
    }

    public function save(User $user, array $data, ?string $id = null): CustomJobQuote
    {
        try {
            return DB::transaction(function () use ($user, $data, $id) {
                $store = $this->context->store($user, $data['input']['store_id']);
                $store = Store::whereKey($store->id)->lockForUpdate()->firstOrFail();
                $hash = $this->hash($data);
                if ($id) {
                    $row = $this->accessible($user, $id, true);
                    $this->version($row, $data['version']);
                    abort_unless($row->status !== 'converted', 409, 'La cotización ya fue convertida.');
                    abort_unless($row->store_id === $store->id, 422, 'La sucursal de una cotización no puede cambiar.');
                } else {
                    $row = CustomJobQuote::where('request_id', $data['request_id'])->first();
                    if ($row) {
                        $this->context->store($user, $row->store_id);
                        abort_unless(hash_equals($row->request_hash, $hash), 409, 'La solicitud ya se usó con otros datos.');

                        return $row;
                    }
                }
                $client = isset($data['client_id']) ? Client::where('status', 'active')->findOrFail($data['client_id']) : null;
                $calculation = $this->calculator->calculate($data['input'], $this->context->commercial($store, $data['input']['currency']), $this->context->inventories($user)->pluck('id')->all());
                if (! $id) {
                    $store->custom_job_quote_number++;
                    $store->save();
                    $row = new CustomJobQuote;
                    $row->fill(['store_id' => $store->id, 'number' => 'COT-'.str_pad($store->custom_job_quote_number, 6, '0', STR_PAD_LEFT),
                        'version' => 1, 'request_id' => $data['request_id'], 'request_hash' => $hash]);
                } else {
                    $row->version++;
                }
                $row->fill(['client_id' => $client?->id, 'client_name' => $client?->name ?? $data['client_name'], 'status' => 'draft'])->save();
                $revisionNumber = ($row->revisions()->max('number') ?? 0) + 1;
                $public = [
                    'schema_version' => 1, 'quote_number' => $row->number, 'revision_number' => $revisionNumber,
                    'client' => ['id' => $client?->id, 'name' => $row->client_name, 'cedula_ruc' => $client?->cedula_ruc],
                    'store' => ['id' => $store->id, 'name' => $store->name], 'note' => $data['note'] ?? '',
                    'currency' => $calculation['commercial']['currency'], 'exchange_rate' => $calculation['commercial']['usd_exchange_rate'],
                    'articles' => array_map(fn ($a) => ['name' => $a['name'], 'quantity' => $a['quantity'], 'unit_price' => $a['unit_price'],
                        'price' => $a['price'], 'discount' => $a['discount'], 'net_price' => $a['net_price']], $calculation['articles']),
                    'totals' => array_intersect_key($calculation['totals'], array_flip(['subtotal', 'discount', 'net', 'tax', 'total'])),
                ];
                $row->revisions()->create(['number' => $revisionNumber, 'user_id' => $user->id,
                    'snapshot' => $calculation['snapshot'], 'commercial' => $public]);

                return $row;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // Different store locks may race on the organization-wide retry key.
            if ($id || ! ($row = CustomJobQuote::where('request_id', $data['request_id'])->first())) {
                throw $exception;
            }
            $this->context->store($user, $row->store_id);
            abort_unless(hash_equals($row->request_hash, $this->hash($data)), 409, 'La solicitud ya se usó con otros datos.');

            return $row;
        }
    }

    private function hash(array $data): string
    {
        $sort = function (array $a) use (&$sort): array {
            if (! array_is_list($a)) {
                ksort($a);
            }
            foreach ($a as &$value) {
                if (is_array($value)) {
                    $value = $sort($value);
                }
            }

            return $a;
        };

        return hash('sha256', json_encode($sort($data), JSON_THROW_ON_ERROR));
    }

    private function version(CustomJobQuote $row, int $version): void
    {
        abort_unless($row->version === $version, 409, 'La cotización cambió. Recarga antes de continuar.');
    }

    public function issue(User $user, string $id, array $data): CustomJobQuote
    {
        return DB::transaction(function () use ($user, $id, $data) {
            // Serialize by store, as on save, so revision edits and emission cannot cross.
            $read = $this->accessible($user, $id);
            $store = Store::whereKey($read->store_id)->lockForUpdate()->firstOrFail();
            $row = $this->accessible($user, $id, true);
            $revision = $row->revisions()->firstOrFail();
            abort_unless($revision->id === $data['revision_id'], 409, 'Selecciona la revisión vigente.');
            if ($row->status === 'issued' && $row->issued_revision_id === $revision->id) {
                return $row;
            }
            $this->version($row, $data['version']);
            abort_unless($row->status === 'draft', 409, 'La cotización no está en borrador.');
            $invoice = $row->proforma()->lockForUpdate()->first();
            if (! $invoice) {
                abort_unless(PlanLimits::for(TenantManager::getTenant())->canCreateInvoice(), 403, 'Has alcanzado el límite de facturas mensuales de tu plan.');
                $next = (int) $store->invoice_number;
                do {
                    $next++;
                    $number = ($store->invoice_prefix ? $store->invoice_prefix.'-' : '').str_pad($next, 6, '0', STR_PAD_LEFT);
                } while (Invoice::where('store_id', $store->id)->where('invoice_number', $number)->exists());
                $store->invoice_number = $next;
                $store->save();
                $invoice = new Invoice;
                $invoice->fill(['user_id' => $user->id, 'store_id' => $store->id, 'invoice_number' => $number, 'source' => 'ADMIN']);
                $invoice->custom_job_quote_id = $row->id;
            }
            $public = $revision->commercial;
            $invoice->fill(['client_id' => $row->client_id, 'client_name' => $public['client']['name'], 'invoice_date' => now()->toDateString(),
                'invoice_note' => $public['note'], 'total' => array_sum(array_column($public['articles'], 'quantity')),
                'discount' => $public['totals']['discount'], 'tax' => $public['totals']['tax'], 'grand_total' => $public['totals']['total'],
                'invoice_type' => 'proforma', 'invoice_status' => 'proforma', 'payment_method' => 'PROFORMA',
                'paid_in_nio' => 0, 'paid_in_usd' => 0, 'exchange_rate' => $public['exchange_rate'], 'cash_session_id' => null, 'payment_metadata' => null]);
            $invoice->custom_job_revision_id = $revision->id;
            $invoice->custom_job_commercial = $public;
            $invoice->save();
            $row->issued_revision_id = $revision->id;
            $row->status = 'issued';
            $row->version++;
            $row->save();

            return $row;
        });
    }

    public function duplicate(User $user, string $id, array $data): CustomJobQuote
    {
        $row = $this->accessible($user, $id);
        $revision = $row->revisions()->firstOrFail();
        abort_unless($revision->id === $data['revision_id'], 409, 'Selecciona la revisión vigente.');

        return $this->save($user, ['request_id' => $data['request_id'], 'client_id' => $row->client_id,
            'client_name' => $revision->commercial['client']['name'], 'note' => $revision->commercial['note'], 'input' => $revision->snapshot['input']]);
    }

    public function representation(User $user, CustomJobQuote $row, bool $history = true): array
    {
        $costs = $user->roles()->where('organization_id', TenantManager::getTenantId())->whereHas('permissions', fn ($q) => $q->where('name', 'custom_job.costs'))->exists();
        $revisions = $row->revisions()->when(! $history, fn ($q) => $q->limit(1))->get();
        $proforma = $row->proforma()->first();

        return [...$row->only(['id', 'store_id', 'client_id', 'client_name', 'number', 'status', 'version', 'issued_revision_id', 'created_at', 'updated_at']),
            'proforma' => $proforma ? ['id' => $proforma->id, 'number' => $proforma->invoice_number, 'revision_id' => $proforma->custom_job_revision_id] : null,
            'revisions' => $revisions->map(fn ($r) => [...$r->only(['id', 'number', 'user_id', 'created_at']), 'commercial' => $r->commercial,
                ...($costs ? ['snapshot' => $r->snapshot] : [])])->all(),
            // Plan 07 consumes this exact immutable revision; no order is created by plan 06.
            'conversion' => ['available' => false, 'revision_id' => $row->status === 'issued' ? $row->issued_revision_id : null, 'version' => $row->version]];
    }
}
