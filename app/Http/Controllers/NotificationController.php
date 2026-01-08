<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
// use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{

public function sendNotification(Request $request)
    {
        $signature = (string) $request->header('X-Schomann-Signature');
        $secret = env('SCHOMANN_DATA_WEBHOOK_SECRET');

        if ($signature === '') {
            abort(Response::HTTP_UNAUTHORIZED, 'Missing signature');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid signature');
        }

        response()->json(['ok' => true])->send();

        // po wysłaniu odpowiedzi dopiero wysyłasz telegram
        $this->notifyTelegram($request);
    }

    public function notifyTelegram(Request $request)
    {

        $telegram = new Api(env('TELEGRAM_BOT_API'));

        $date = date('d-m-Y');

        $text = "<b>🛒 Nowe zamówienie</b>\n\n"
            . "📅 <b>Data:</b> {$date}\n"
            . "🆔 <b>ID zamówienia:</b> {$request->input('id')}\n"
            . "💰 <b>Kwota:</b> {$request->input('total')} zł\n"
            . "📧 <b>Email klienta:</b> {$request->input('customer_email')}";

        $telegram->sendMessage([
            'chat_id' => env('TELEGRAM_CHANNEL_ID'),
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);
    }
}
