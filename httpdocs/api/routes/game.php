<?php
/**
 * Shimlar API — Game Action Routes
 * POST /api/game/action
 * POST /api/game/move
 * POST /api/game/fight
 * POST /api/game/heal
 * POST /api/game/bank
 */

require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../incz/constvars.inc';

init_dbx();

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') api_error('Method not allowed', 405);

// Authenticate via session
api_session_start();
if (empty($_SESSION['logged_in']) || empty($_SESSION['player_id'])) {
    api_error('Not authenticated', 401);
    exit;
}
$pid = (int)$_SESSION['player_id'];

$path = trim($_GET['route'] ?? '', '/');
$route = preg_replace('#^game/#', '', $path);

switch ($route) {
    case 'action':
        $action = api_get('action');
        if (!$action) api_error('Action required', 400);
        $result = api_process_action($pid, $action, api_get('c1', 0), api_get('c2', 0), api_get('c3', 0));
        api_success($result);
        break;

    case 'move':
        $direction = (int)api_get('direction', 0);
        if ($direction < 1 || $direction > 4) api_error('Direction must be 1-4 (N/S/E/W)', 400);
        api_success(api_process_action($pid, 'm', $direction, 0, 0));
        break;

    case 'fight':
        api_success(api_process_action($pid, 'f', 0, 0, 0));
        break;

    case 'cast':
        api_success(api_process_action($pid, 'c', api_get('spell', 0), 0, 0));
        break;

    case 'heal':
        api_success(api_process_action($pid, 'he', 0, 0, 0));
        break;

    case 'rest':
        api_success(api_process_action($pid, 'r', 0, 0, 0));
        break;

    case 'overview':
        api_success(api_process_action($pid, 'o', 0, 0, 0));
        break;

    case 'bank':
        $bankAction = api_get('action', '');
        $amount = (int)api_get('amount', 0);

        $statsQ = mysqli_query($dbx, "SELECT Gold, Banked FROM Stats WHERE Id=$pid");
        $stats = mysqli_fetch_assoc($statsQ);
        $gold = (int)$stats['Gold'];
        $banked = (int)$stats['Banked'];

        switch ($bankAction) {
            case 'deposit':
                if ($amount <= 0 || $amount > $gold) api_error('Invalid amount', 400);
                mysqli_query($dbx, "UPDATE Stats SET Gold=Gold-$amount, Banked=Banked+$amount WHERE Id=$pid");
                $gold -= $amount; $banked += $amount;
                break;
            case 'withdraw':
                if ($amount <= 0 || $amount > $banked) api_error('Invalid amount', 400);
                mysqli_query($dbx, "UPDATE Stats SET Gold=Gold+$amount, Banked=Banked-$amount WHERE Id=$pid");
                $gold += $amount; $banked -= $amount;
                break;
            case 'deposit_all':
                $amount = $gold;
                if ($amount > 0) {
                    mysqli_query($dbx, "UPDATE Stats SET Gold=0, Banked=Banked+$amount WHERE Id=$pid");
                    $banked += $gold; $gold = 0;
                }
                break;
            case 'withdraw_all':
                $amount = $banked;
                if ($amount > 0) {
                    mysqli_query($dbx, "UPDATE Stats SET Gold=Gold+$amount, Banked=0 WHERE Id=$pid");
                    $gold += $banked; $banked = 0;
                }
                break;
            default:
                api_error('Bank action required: deposit, withdraw, deposit_all, withdraw_all', 400);
        }

        api_success(['gold' => $gold, 'banked' => $banked, 'action' => $bankAction, 'amount' => $amount]);
        break;

    default:
        api_error("Unknown game route: $route", 404);
}

close_dbx();

function api_load_player($pid) {
    global $dbx;
    $pQ = mysqli_query($dbx, "SELECT * FROM Players WHERE Id=$pid");
    $sQ = mysqli_query($dbx, "SELECT * FROM Stats WHERE Id=$pid");
    return [
        'p' => $pQ ? mysqli_fetch_assoc($pQ) : [],
        's' => $sQ ? mysqli_fetch_assoc($sQ) : [],
    ];
}

function api_player_response($data) {
    $p = $data['p']; $s = $data['s'];
    return [
        'name' => $p['Name'] ?? '',
        'level' => (int)($s['Lvl'] ?? 0),
        'health' => (int)($s['Health'] ?? 0),
        'e_health' => (int)($s['E_health'] ?? 0),
        'gold' => (int)($s['Gold'] ?? 0),
        'banked' => (int)($s['Banked'] ?? 0),
        'exp' => (int)($s['Exp'] ?? 0),
        'turns' => (int)($s['Turns'] ?? 0),
    ];
}

function api_process_action($pid, $action, $c1, $c2, $c3) {
    global $dbx;

    mysqli_query($dbx, "LOCK TABLES Players WRITE, Stats WRITE, Inventory WRITE, Zones READ, Housing WRITE, Messages WRITE, Transfers WRITE, Market WRITE, Quests READ, Clans READ, Kingdoms WRITE, Races READ");

    $data = api_load_player($pid);
    $p = $data['p']; $s = $data['s'];

    $result = [
        'action' => $action,
        'player' => api_player_response($data),
        'location' => [
            'x' => (int)($p['Loc_x'] ?? 1),
            'y' => (int)($p['Loc_y'] ?? 1),
            'zone' => (int)($p['Loc_zone'] ?? 1),
        ],
        'messages' => [],
    ];

    switch ($action) {
        case 'o': // Overview
            $zone = (int)($p['Loc_zone'] ?? 1);
            $zQ = mysqli_query($dbx, "SELECT * FROM Zones WHERE Zid=$zone");
            $result['zone'] = $zQ ? mysqli_fetch_assoc($zQ) : null;
            $result['player']['str'] = (int)($s['Str'] ?? 0);
            $result['player']['dex'] = (int)($s['Dex'] ?? 0);
            $result['player']['vit'] = (int)($s['Vit'] ?? 0);
            $result['player']['ntl'] = (int)($s['Ntl'] ?? 0);
            $result['player']['wis'] = (int)($s['Wis'] ?? 0);
            $result['player']['freelvl'] = (int)($s['Freelvl'] ?? 0);
            $result['player']['clan'] = (int)($s['Clan'] ?? 0);
            $result['player']['align'] = (int)($s['Align'] ?? 0);
            $result['player']['opponent'] = (int)($s['Opponent'] ?? 0);
            $result['player']['status'] = (int)($s['Status'] ?? 0);
            $result['messages'][] = "Overview loaded";
            break;

        case 'm': // Move
            $x = (int)($p['Loc_x'] ?? 1);
            $y = (int)($p['Loc_y'] ?? 1);
            $zone = (int)($p['Loc_zone'] ?? 1);
            switch ((int)$c1) {
                case 1: $y--; break;
                case 2: $y++; break;
                case 3: $x++; break;
                case 4: $x--; break;
            }
            $x = max(1, min(20, $x));
            $y = max(1, min(20, $y));
            mysqli_query($dbx, "UPDATE Players SET Loc_x=$x, Loc_y=$y WHERE Id=$pid");
            $result['location'] = ['x' => $x, 'y' => $y, 'zone' => $zone];
            $result['messages'][] = "Moved to ($x, $y)";
            break;

        case 'he': // Heal
            $health = (int)($s['Health'] ?? 0);
            $vit = (int)($s['Vit'] ?? 0);
            $maxHp = $vit * 10;
            if ($health >= $maxHp) {
                $result['messages'][] = "Already at full health";
            } else {
                $newHp = min($maxHp, $health + floor($maxHp * 0.3));
                mysqli_query($dbx, "UPDATE Stats SET Health=$newHp WHERE Id=$pid");
                $result['player']['health'] = $newHp;
                $result['messages'][] = "Healed to $newHp HP";
            }
            break;

        case 'r': // Rest
            $health = (int)($s['Health'] ?? 0);
            $vit = (int)($s['Vit'] ?? 0);
            $maxHp = $vit * 10;
            $newHp = min($maxHp, $health + floor($maxHp * 0.1));
            mysqli_query($dbx, "UPDATE Stats SET Health=$newHp WHERE Id=$pid");
            $result['player']['health'] = $newHp;
            $result['messages'][] = "Rested, HP now $newHp";
            break;

        default:
            $result['messages'][] = "Action '$action' not yet implemented in API";
            break;
    }

    mysqli_query($dbx, "UPDATE Players SET Lastaction='api', Ax_time=NOW() WHERE Id=$pid");
    mysqli_query($dbx, "UNLOCK TABLES");

    return $result;
}
