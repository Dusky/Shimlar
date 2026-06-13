<?php
/**
 * Shimlar API — Combat Routes
 * POST /api/game/fight
 * POST /api/game/cast
 * POST /api/game/newfight
 * POST /api/game/newduel
 * 
 * These wrap the existing battle system (batx4.inc) by capturing
 * its JS output and converting it to structured JSON.
 */

require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../incz/constvars.inc';

init_dbx();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_error('Method not allowed', 405);

api_session_start();
if (empty($_SESSION['logged_in']) || empty($_SESSION['player_id'])) {
    api_error('Not authenticated', 401);
    exit;
}
$pid = (int)$_SESSION['player_id'];

$path = trim($_GET['route'] ?? '', '/');
$route = preg_replace('#^game/#', '', $path);

switch ($route) {
    case 'fight':
        $result = api_do_combat($pid, 'f', 0, 0);
        api_success($result);
        break;

    case 'cast':
        $result = api_do_combat($pid, 'c', 0, 0);
        api_success($result);
        break;

    case 'newfight':
        $result = api_do_combat($pid, 'n', 0, 0);
        api_success($result);
        break;

    case 'newduel':
        $result = api_do_combat($pid, 'd', 0, 0);
        api_success($result);
        break;

    default:
        api_error("Unknown combat route: $route", 404);
}

close_dbx();

/**
 * Execute combat by wrapping the original battle system.
 * Captures JS output and parses it into structured data.
 */
function api_do_combat($pid, $action, $c1, $c2) {
    global $dbx;

    // Verify player exists and get password for battle auth
    $pQ = mysqli_query($dbx, "SELECT Id, Password, Name FROM Players WHERE Id=$pid");
    if (!$pQ || mysqli_num_rows($pQ) !== 1) api_error('Player not found', 404);
    $player = mysqli_fetch_assoc($pQ);

    // Load current stats before combat
    $preStats = api_load_stats($pid);

    // Cast parameters to strings (original code expects string POST values)
    $l = (string)$pid;
    $p = (string)$player['Password'];
    $a = (string)$action;
    $k = (string)$c1;
    // Anti-macro check: newfight expects $m to match fms[fmt] from Stats table
    // fms = "http://www.shimlar.org/" — char at index fmt
    // For API use, compute the correct value automatically
    $sFmtQ = mysqli_query($dbx, "SELECT Fmt FROM Stats WHERE Id=$pid");
    $fmtRow = mysqli_fetch_assoc($sFmtQ);
    $fms = "http://www.shimlar.org/";
    $fmtIdx = (int)($fmtRow['Fmt'] ?? 0);
    $m = $fms[$fmtIdx] ?? 'h';

    // Capture output from the battle system
    ob_start();

    // Suppress PHP warnings/deprecations from legacy code
    $prevErrorLevel = error_reporting(E_ERROR | E_PARSE);

    // Override REQUEST_URI so battle system's security check passes
    $origRequestUri = $_SERVER['REQUEST_URI'];
    $_SERVER['REQUEST_URI'] = '/battle4.php';

    // Include batx4 which defines batproc()
    require_once __DIR__ . '/../../../incz/batx4.inc';
    
    // Ensure $dbx is available globally for batproc (which doesn't declare global $dbx)
    $GLOBALS['dbx'] = $dbx;
    
    // Call the battle processor directly
    batproc($l, $p, $a, $k, $m);

    $_SERVER['REQUEST_URI'] = $origRequestUri;
    error_reporting($prevErrorLevel);
    $output = ob_get_clean();

    // Load stats after combat
    $postStats = api_load_stats($pid);

    // Parse the JS output into combat log
    $combatLog = api_parse_battle_output($output);

    return [
        'action' => $action,
        'combatLog' => $combatLog,
        'rawOutput' => $output, // for debugging, can remove later
        'before' => $preStats,
        'after' => $postStats,
        'changes' => [
            'health' => $postStats['health'] - $preStats['health'],
            'exp' => $postStats['exp'] - $preStats['exp'],
            'gold' => $postStats['gold'] - $preStats['gold'],
            'e_health' => $postStats['e_health'],
        ],
    ];
}

function api_load_stats($pid) {
    global $dbx;
    $sQ = mysqli_query($dbx, "SELECT * FROM Stats WHERE Id=$pid");
    $s = $sQ ? mysqli_fetch_assoc($sQ) : [];
    return [
        'health' => (int)($s['Health'] ?? 0),
        'e_health' => (int)($s['E_health'] ?? 0),
        'exp' => (int)($s['Exp'] ?? 0),
        'gold' => (int)($s['Gold'] ?? 0),
        'lvl' => (int)($s['Lvl'] ?? 0),
        'status' => (int)($s['Status'] ?? 0),
        'opponent' => (int)($s['Opponent'] ?? 0),
        'turns' => (int)($s['Turns'] ?? 0),
    ];
}

/**
 * Parse the JS function calls from battle output into a structured log.
 * 
 * Expected JS patterns:
 *   x1(exp,gold,health,align) — stat update
 *   x2(monster,lastfight,"name","battle_string") — combat result
 *   x3("inv_string") — inventory update
 *   x10(turns) — turns update
 *   mX(type) — mastery gain
 *   xX("action",0) — action confirm
 */
function api_parse_battle_output($output) {
    $log = [
        'statUpdate' => null,
        'combatResult' => null,
        'inventoryUpdate' => null,
        'masteryGains' => [],
        'raw' => [],
    ];

    // Parse x1(exp,gold,health,align)
    if (preg_match('/x1\((\d+),(\d+),(\d+),(-?\d+)\)/', $output, $m)) {
        $log['statUpdate'] = [
            'exp' => (int)$m[1],
            'gold' => (int)$m[2],
            'health' => (int)$m[3],
            'align' => (int)$m[4],
        ];
    }

    // Parse x2(monster,lastfight,"name","battle_string")
    if (preg_match('/x2\((\d+),(\d+),"?([^"]*)"?,\s*"?([^"]*)\"?\)/', $output, $m)) {
        $log['combatResult'] = [
            'monsterType' => (int)$m[1],
            'lastFight' => (int)$m[2],
            'opponentName' => $m[3],
            'battleString' => $m[4],
        ];

        // Decode the battle string into events
        $log['combatResult']['events'] = api_decode_battle_string($m[4]);
    }

    // Parse x3("inv_string")
    if (preg_match('/x3\("([^"]+)"\)/', $output, $m)) {
        $log['inventoryUpdate'] = $m[1];
    }

    // Parse x10(turns)
    if (preg_match('/x10\((\d+)\)/', $output, $m)) {
        $log['turnsUpdate'] = (int)$m[1];
    }

    // Parse mX(type) — mastery gains
    if (preg_match_all('/mX\((\d+)\)/', $output, $matches)) {
        foreach ($matches[1] as $type) {
            $log['masteryGains'][] = (int)$type;
        }
    }

    return $log;
}

/**
 * Decode the battle string into a list of combat events.
 * 
 * Battle string codes:
 *   u = user attacked, e = enemy attacked
 *   fX,damage = hit with weapon type X
 *   fcX,damage = critical hit with weapon type X
 *   sX,damage = spell hit with element X
 *   scX,damage = critical spell hit
 *   h,damage = heal
 *   m = miss
 *   t = spell miss/resist
 *   y = heal had no effect
 *   r = player died
 *   vd = ghost can't fight
 *   vx = no enemy
 *   b,health = bounty hunt enemy health
 *   v[codes] = various error states
 */
function api_decode_battle_string($str) {
    $events = [];
    $tokens = explode(',', $str);
    $i = 0;
    while ($i < count($tokens)) {
        $token = $tokens[$i];
        
        if ($token === 'u' || $token === 'e') {
            $who = $token === 'u' ? 'player' : 'enemy';
            $i++;
            if ($i >= count($tokens)) break;
            $next = $tokens[$i];
            
            if (preg_match('/^f(\d+)$/', $next, $m)) {
                $i++;
                $damage = isset($tokens[$i]) ? (int)$tokens[$i] : 0;
                $events[] = ['who' => $who, 'type' => 'weapon_hit', 'element' => (int)$m[1], 'damage' => $damage, 'critical' => false];
            } else if (preg_match('/^fc(\d+)$/', $next, $m)) {
                $i++;
                $damage = isset($tokens[$i]) ? (int)$tokens[$i] : 0;
                $events[] = ['who' => $who, 'type' => 'weapon_hit', 'element' => (int)$m[1], 'damage' => $damage, 'critical' => true];
            } else if (preg_match('/^s(\d+)$/', $next, $m)) {
                $i++;
                $damage = isset($tokens[$i]) ? (int)$tokens[$i] : 0;
                $events[] = ['who' => $who, 'type' => 'spell_hit', 'element' => (int)$m[1], 'damage' => $damage, 'critical' => false];
            } else if (preg_match('/^sc(\d+)$/', $next, $m)) {
                $i++;
                $damage = isset($tokens[$i]) ? (int)$tokens[$i] : 0;
                $events[] = ['who' => $who, 'type' => 'spell_hit', 'element' => (int)$m[1], 'damage' => $damage, 'critical' => true];
            } else if ($next === 'm') {
                $events[] = ['who' => $who, 'type' => 'miss'];
            } else if ($next === 't') {
                $events[] = ['who' => $who, 'type' => 'spell_miss'];
            } else if ($next === 'h') {
                $i++;
                $damage = isset($tokens[$i]) ? (int)$tokens[$i] : 0;
                $events[] = ['who' => $who, 'type' => 'heal', 'damage' => $damage];
            } else if ($next === 'y') {
                $events[] = ['who' => $who, 'type' => 'heal_no_effect'];
            }
        } else if ($token === 'r') {
            $events[] = ['who' => 'player', 'type' => 'died'];
        } else if ($token === 'vd') {
            $events[] = ['type' => 'error_ghost'];
        } else if ($token === 'vx') {
            $events[] = ['type' => 'error_no_enemy'];
        } else if ($token === 'vn') {
            $events[] = ['type' => 'error_zone'];
        } else if ($token === 'vm') {
            $events[] = ['type' => 'error_pvp_zone'];
        } else if (preg_match('/^b,(\d+)$/', $token, $m)) {
            $events[] = ['type' => 'bounty_health', 'health' => (int)$m[1]];
        } else if (preg_match('/^vp(\d+)$/', $token, $m)) {
            $events[] = ['type' => 'error_pk_cooldown', 'seconds' => (int)$m[1]];
        } else if ($token === 'vt') {
            $events[] = ['type' => 'error_target_moved'];
        } else if ($token === 'vz') {
            $events[] = ['type' => 'error_target_dead'];
        }
        
        $i++;
    }

    return $events;
}
