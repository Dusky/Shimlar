<?php
/**
 * Shimlar API — Chat Routes
 * GET  /api/chat/messages
 * POST /api/chat/send
 * POST /api/chat/channel
 */

require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../incz/constvars.inc';

init_dbx();

$player = api_require_auth();
$pid = (int)$player['Id'];

$path = trim($_GET['route'] ?? '', '/');
$route = preg_replace('#^chat/#', '', $path);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if ($route === 'messages') {
            $channel = (int)api_get('channel', 1);
            $chatTable = 'chat' . max(1, min(4, $channel));

            $last = (int)api_get('after', 0);
            $query = "SELECT * FROM $chatTable";
            if ($last > 0) {
                $query .= " WHERE msgnum > $last";
            }
            $query .= " ORDER BY msgnum DESC LIMIT 50";

            $result = mysqli_query($dbx, $query);
            $messages = [];
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $messages[] = [
                        'id' => (int)$row['msgnum'],
                        'name' => $row['msgname'],
                        'text' => $row['msgtxt'],
                        'type' => $row['msgtype'],
                    ];
                }
            }
            $messages = array_reverse($messages); // chronological order

            api_success([
                'channel' => $channel,
                'messages' => $messages,
                'lastId' => !empty($messages) ? $messages[count($messages)-1]['id'] : $last,
            ]);
        }
        break;

    case 'POST':
        switch ($route) {
            case 'send':
                $message = trim(api_get('message', ''));
                if (empty($message)) api_error('Message required', 400);
                if (strlen($message) > 190) api_error('Message too long (190 chars max)', 400);

                $channel = (int)($player['Channels'] ?? 1);
                $chatTable = 'chat' . max(1, min(4, $channel));
                $name = $player['Name'];

                // Check if it's a command (starts with /)
                if ($message[0] === '/') {
                    // Route to command processor
                    $name = mysqli_real_escape_string($dbx, $name);
                    $msg = mysqli_real_escape_string($dbx, $message);
                    // For now, just post it — full command processing can be added later
                    mysqli_query($dbx, "INSERT INTO $chatTable (msgname, msgtxt, msgtype) VALUES ('$name', '$msg', '1')");
                } else {
                    $name = mysqli_real_escape_string($dbx, $name);
                    $msg = mysqli_real_escape_string($dbx, $message);
                    mysqli_query($dbx, "INSERT INTO $chatTable (msgname, msgtxt, msgtype) VALUES ('$name', '$msg', '1')");
                }

                api_success(null, 'Message sent');
                break;

            case 'channel':
                $channel = (int)api_get('channel', 1);
                if ($channel < 1 || $channel > 4) api_error('Channel must be 1-4', 400);

                mysqli_query($dbx, "UPDATE Players SET Channels=$channel WHERE Id=$pid");
                api_success(['channel' => $channel], 'Channel switched');
                break;

            default:
                api_error("Unknown chat route: $route", 404);
        }
        break;

    default:
        api_error('Method not allowed', 405);
}

close_dbx();
