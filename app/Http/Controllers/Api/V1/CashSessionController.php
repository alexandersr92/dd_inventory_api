<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\CashTransaction;
use App\Models\Invoice;
use App\Models\CreditDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CashSessionController extends Controller
{
    /**
     * List all cash sessions for the organization.
     */
    public function index(Request $request)
    {
        $orgId = Auth::user()->organization_id;
        
        $query = CashSession::whereHas('store', function ($q) use ($orgId) {
            $q->where('organization_id', $orgId);
        });

        if ($request->store_id) {
            $query->where('store_id', $request->store_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->user_id) {
            $query->where('user_id', $request->user_id);
        }

        $sessions = $query->with(['store', 'user', 'cashTransactions'])
            ->orderBy('opened_at', 'desc')
            ->paginate($request->pageSize ?? 15);

        return response()->json($sessions);
    }

    /**
     * Open a new cash session.
     */
    public function open(Request $request)
    {
        $request->validate([
            'store_id' => 'required|uuid|exists:stores,id',
            'opening_balance' => 'required|numeric|min:0',
            'cash_register_name' => 'nullable|string|max:50',
        ]);

        $userId = Auth::user()->id;

        // Check if user already has an active session in this store
        $activeSession = CashSession::where('store_id', $request->store_id)
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        if ($activeSession) {
            return response()->json([
                'message' => 'Ya tienes una sesión de caja abierta en esta sucursal.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $session = CashSession::create([
            'store_id' => $request->store_id,
            'user_id' => $userId,
            'cash_register_name' => $request->cash_register_name,
            'opening_balance' => $request->opening_balance,
            'expected_balance' => $request->opening_balance,
            'status' => 'open',
            'opened_at' => now(),
        ]);

        return response()->json([
            'message' => 'Caja abierta con éxito.',
            'session' => $session
        ], Response::HTTP_CREATED);
    }

    /**
     * Get details of the active session including real-time totals.
     */
    public function active(Request $request)
    {
        $request->validate([
            'store_id' => 'required|uuid|exists:stores,id',
        ]);

        $userId = Auth::user()->id;

        $session = CashSession::where('store_id', $request->store_id)
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->first();

        if (!$session) {
            return response()->json([
                'message' => 'No tienes ninguna sesión de caja activa.',
                'is_open' => false
            ], Response::HTTP_OK);
        }

        $session->load('cashTransactions');

        $totals = $this->computeSessionTotals($session);

        return response()->json([
            'is_open' => true,
            'session' => $session,
            'totals' => $totals
        ], Response::HTTP_OK);
    }

    /**
     * Add manual cash transaction (income / expense).
     */
    public function addTransaction(Request $request)
    {
        $request->validate([
            'cash_session_id' => 'required|uuid|exists:cash_sessions,id',
            'type' => 'required|in:in,out',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'currency' => 'nullable|string|in:NIO,USD',
            'expense_category_id' => 'nullable|uuid|exists:expense_categories,id',
        ]);

        $session = CashSession::findOrFail($request->cash_session_id);

        if ($session->status !== 'open') {
            return response()->json([
                'message' => 'La sesión de caja está cerrada.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $transaction = CashTransaction::create([
            'cash_session_id' => $session->id,
            'type' => $request->type,
            'amount' => $request->amount,
            'currency' => $request->currency ?? 'NIO',
            'expense_category_id' => $request->expense_category_id,
            'user_id' => Auth::user()->id,
            'description' => $request->description,
        ]);

        // Update session expected balance
        $totals = $this->computeSessionTotals($session);
        $session->expected_balance = $totals['expected_cash'];
        $session->save();

        return response()->json([
            'message' => 'Movimiento registrado con éxito.',
            'transaction' => $transaction,
            'expected_balance' => $session->expected_balance
        ], Response::HTTP_CREATED);
    }

    /**
     * Close the session and register count discrepancy.
     */
    public function close(Request $request)
    {
        $request->validate([
            'cash_session_id' => 'required|uuid|exists:cash_sessions,id',
            'actual_cash' => 'required|numeric|min:0',
            'actual_usd' => 'nullable|numeric|min:0',
            'usd_exchange_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $session = CashSession::findOrFail($request->cash_session_id);

        if ($session->status !== 'open') {
            return response()->json([
                'message' => 'La sesión de caja ya se encuentra cerrada.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $totals = $this->computeSessionTotals($session);
        $expected = $totals['expected_cash'];
        $actual_nio = $request->actual_cash;
        $actual_usd = $request->actual_usd ?? 0;
        $rate = $request->usd_exchange_rate ?? 0;

        $actual_total_nio = $actual_nio + ($actual_usd * $rate);
        $diff = $actual_total_nio - $expected;

        $session->update([
            'status' => 'closed',
            'expected_balance' => $expected,
            'actual_cash' => $actual_nio,
            'actual_usd' => $actual_usd,
            'usd_exchange_rate' => $rate,
            'difference' => $diff,
            'closed_at' => now(),
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Caja cerrada y turnos auditados correctamente.',
            'session' => $session,
            'totals' => $totals
        ], Response::HTTP_OK);
    }

    /**
     * Update a cash session closure (only owner).
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        // 1. Authorize owner
        if (!$user->organization || $user->id !== $user->organization->owner_id) {
            return response()->json([
                'message' => 'Solo el propietario (owner) puede editar un cierre de caja.'
            ], Response::HTTP_FORBIDDEN);
        }

        // 2. Find and check organization
        $session = CashSession::whereHas('store', function ($q) use ($user) {
            $q->where('organization_id', $user->organization_id);
        })->findOrFail($id);

        // 3. Ensure the session is closed
        if ($session->status !== 'closed') {
            return response()->json([
                'message' => 'Solo se pueden editar sesiones de caja cerradas.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // 4. Validate input
        $request->validate([
            'actual_cash' => 'required|numeric|min:0',
            'actual_usd' => 'nullable|numeric|min:0',
            'usd_exchange_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $actual_nio = $request->actual_cash;
        $actual_usd = $request->actual_usd ?? $session->actual_usd ?? 0;
        $rate = $request->usd_exchange_rate ?? $session->usd_exchange_rate ?? 0;

        $expected = $session->expected_balance;
        $actual_total_nio = $actual_nio + ($actual_usd * $rate);
        $diff = $actual_total_nio - $expected;

        // 5. Update
        $session->update([
            'actual_cash' => $actual_nio,
            'actual_usd' => $actual_usd,
            'usd_exchange_rate' => $rate,
            'difference' => $diff,
            'notes' => $request->notes,
        ]);

        return response()->json([
            'message' => 'Cierre de caja actualizado con éxito.',
            'session' => $session->load(['store', 'user', 'cashTransactions'])
        ], Response::HTTP_OK);
    }

    /**
     * Update a cash transaction (only owner for closed session, cashier for open session).
     */
    public function updateTransaction(Request $request, $id)
    {
        $user = Auth::user();

        // 1. Find transaction and check organization
        $transaction = CashTransaction::whereHas('cashSession.store', function ($q) use ($user) {
            $q->where('organization_id', $user->organization_id);
        })->findOrFail($id);

        $session = $transaction->cashSession;

        // 2. Authorize
        if ($session->status === 'closed') {
            if (!$user->organization || $user->id !== $user->organization->owner_id) {
                return response()->json([
                    'message' => 'Solo el propietario (owner) puede editar movimientos de un cierre de caja.'
                ], Response::HTTP_FORBIDDEN);
            }
        } else {
            if ($session->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Solo el cajero asignado a este turno puede editar sus movimientos.'
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // 3. Validate input
        $request->validate([
            'type' => 'required|in:in,out',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'currency' => 'nullable|string|in:NIO,USD',
            'expense_category_id' => 'nullable|uuid|exists:expense_categories,id',
        ]);

        // 4. Update transaction
        $transaction->update([
            'type' => $request->type,
            'amount' => $request->amount,
            'currency' => $request->currency ?? $transaction->currency,
            'expense_category_id' => $request->has('expense_category_id') ? $request->expense_category_id : $transaction->expense_category_id,
            'description' => $request->description,
        ]);

        // 5. Recalculate session totals and balances
        $this->recalculateSessionBalances($session);

        return response()->json([
            'message' => 'Movimiento de caja actualizado con éxito.',
            'transaction' => $transaction,
            'session' => $session->fresh(['store', 'user', 'cashTransactions'])
        ], Response::HTTP_OK);
    }

    /**
     * Delete a cash transaction (only owner for closed session, cashier for open session).
     */
    public function destroyTransaction($id)
    {
        $user = Auth::user();

        // 1. Find transaction and check organization
        $transaction = CashTransaction::whereHas('cashSession.store', function ($q) use ($user) {
            $q->where('organization_id', $user->organization_id);
        })->findOrFail($id);

        $session = $transaction->cashSession;

        // 2. Authorize
        if ($session->status === 'closed') {
            if (!$user->organization || $user->id !== $user->organization->owner_id) {
                return response()->json([
                    'message' => 'Solo el propietario (owner) puede eliminar movimientos de un cierre de caja.'
                ], Response::HTTP_FORBIDDEN);
            }
        } else {
            if ($session->user_id !== $user->id) {
                return response()->json([
                    'message' => 'Solo el cajero asignado a este turno puede eliminar sus movimientos.'
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // 3. Delete
        $transaction->delete();

        // 4. Recalculate session totals and balances
        $this->recalculateSessionBalances($session);

        return response()->json([
            'message' => 'Movimiento de caja eliminado con éxito.',
            'session' => $session->fresh(['store', 'user', 'cashTransactions'])
        ], Response::HTTP_OK);
    }

    /**
     * Helper to recalculate and update session balances.
     */
    private function recalculateSessionBalances(CashSession $session)
    {
        $totals = $this->computeSessionTotals($session);
        $expected = $totals['expected_cash'];
        
        $actual_nio = $session->actual_cash;
        $actual_usd = $session->actual_usd ?? 0;
        $rate = $session->usd_exchange_rate ?? 0;

        $actual_total_nio = $actual_nio + ($actual_usd * $rate);
        $diff = $actual_total_nio - $expected;

        $session->update([
            'expected_balance' => $expected,
            'difference' => $diff,
        ]);
    }

    /**
     * Internal calculator helper to aggregate sales, credit abonos, and manual adjustments.
     */
    private function computeSessionTotals(CashSession $session)
    {
        $invoices = Invoice::where('cash_session_id', $session->id)->get();
        $creditDetails = CreditDetail::where('cash_session_id', $session->id)->get();
        
        $invoiceCash = 0;
        $invoiceTransfer = 0;
        $invoiceCard = 0;

        foreach ($invoices as $inv) {
            if ($inv->payment_method === 'CASH') {
                $invoiceCash += $inv->grand_total;
            } elseif ($inv->payment_method === 'TRANSFER') {
                $invoiceTransfer += $inv->grand_total;
            } elseif ($inv->payment_method === 'CARD') {
                $invoiceCard += $inv->grand_total;
            } elseif ($inv->payment_method === 'MULTIPLE' && is_array($inv->payment_metadata) && isset($inv->payment_metadata['payments'])) {
                foreach ($inv->payment_metadata['payments'] as $p) {
                    if (isset($p['method'])) {
                        if ($p['method'] === 'CASH') {
                            $invoiceCash += $p['amount'];
                        } elseif ($p['method'] === 'TRANSFER') {
                            $invoiceTransfer += $p['amount'];
                        } elseif ($p['method'] === 'CARD') {
                            $invoiceCard += $p['amount'];
                        }
                    }
                }
            }
        }

        $creditCash = 0;
        $creditTransfer = 0;
        $creditCard = 0;

        foreach ($creditDetails as $cd) {
            if ($cd->payment_method === 'CASH') {
                $creditCash += $cd->amount;
            } elseif ($cd->payment_method === 'TRANSFER') {
                $creditTransfer += $cd->amount;
            } elseif ($cd->payment_method === 'CARD') {
                $creditCard += $cd->amount;
            } elseif ($cd->payment_method === 'MULTIPLE' && is_array($cd->payment_metadata) && isset($cd->payment_metadata['payments'])) {
                foreach ($cd->payment_metadata['payments'] as $p) {
                    if (isset($p['method'])) {
                        if ($p['method'] === 'CASH') {
                            $creditCash += $p['amount'];
                        } elseif ($p['method'] === 'TRANSFER') {
                            $creditTransfer += $p['amount'];
                        } elseif ($p['method'] === 'CARD') {
                            $creditCard += $p['amount'];
                        }
                    }
                }
            }
        }

        $manualIn = $session->cashTransactions()->where('type', 'in')->sum('amount');
        $manualOut = $session->cashTransactions()->where('type', 'out')->sum('amount');

        $expectedCash = $session->opening_balance + $invoiceCash + $creditCash + $manualIn - $manualOut;

        return [
            'invoice_cash' => $invoiceCash,
            'invoice_transfer' => $invoiceTransfer,
            'invoice_card' => $invoiceCard,
            'credit_cash' => $creditCash,
            'credit_transfer' => $creditTransfer,
            'credit_card' => $creditCard,
            'manual_in' => $manualIn,
            'manual_out' => $manualOut,
            'expected_cash' => $expectedCash,
            'total_transfer' => $invoiceTransfer + $creditTransfer,
            'total_card' => $invoiceCard + $creditCard,
        ];
    }
}
