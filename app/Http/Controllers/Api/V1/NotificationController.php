<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Obtener el listado de notificaciones del usuario autenticado.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->query('per_page', 15);
        $status = $request->query('status'); // 'read' o 'unread'

        $query = $user->notifications();

        if ($status === 'unread') {
            $query = $user->unreadNotifications();
        } elseif ($status === 'read') {
            $query = $user->readNotifications();
        }

        $notifications = $query->paginate($perPage);

        // Transformar la estructura para retornar título, descripción, acción directamente
        $notifications->getCollection()->transform(function ($notification) {
            $data = $notification->data;
            return [
                'id' => $notification->id,
                'event_key' => $data['event_key'] ?? '',
                'title' => $data['title'] ?? 'Notificación del sistema',
                'description' => $data['description'] ?? '',
                'action' => $data['action'] ?? '',
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
            ];
        });

        return response()->json($notifications);
    }

    /**
     * Marca una notificación específica como leída.
     */
    public function markAsRead(string $id)
    {
        $user = Auth::user();
        $notification = $user->unreadNotifications()->where('id', $id)->first();

        if ($notification) {
            $notification->markAsRead();
            return response()->json([
                'message' => 'Notificación marcada como leída.',
                'id' => $id,
            ]);
        }

        return response()->json([
            'message' => 'La notificación ya estaba leída o no existe.',
        ], 200);
    }

    /**
     * Marca todas las notificaciones no leídas del usuario como leídas.
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'Todas las notificaciones han sido marcadas como leídas.',
        ]);
    }

    /**
     * Elimina una notificación del historial del usuario.
     */
    public function destroy(string $id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->first();

        if ($notification) {
            $notification->delete();
            return response()->json([
                'message' => 'Notificación eliminada correctamente.',
                'id' => $id,
            ]);
        }

        return response()->json([
            'message' => 'Notificación no encontrada.',
        ], 404);
    }
}
