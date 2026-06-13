<?php
/**
 * Shimlar API — Training & Leveling Routes
 * POST /api/game/levelup   — level up (choose stat: str/dex/vit/ntl/wis/balanced)
 * POST /api/game/train     — train masteries at trainer (costs gold, must be in trainer zone)
 * GET  /api/game/progress  — show XP progress to next level
 */

require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../incz/constvars.inc';

init_dbx();

api_session_start();
if (empty($_SESSION['logged_in']) || empty($_SESSION['player_id'])) {
    api_error('Not authenticated', 401);
    exit;
}
$pid = (int)$_SESSION['player_id'];

$path = trim($_GET['route'] ?? '', '/');
$route = preg_replace('#^game/#', '', $path);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $route === 'progress') {
    $sQ = mysqli_query($dbx, "SELECT Lvl, Exp, Freelvl, Str, Dex, Vit, Ntl, Wis FROM Stats WHERE Id=$pid");
    $s = mysqli_fetch_assoc($sQ);
    $lvl = (int)$s['Lvl'];
    $exp = (int)$s['Exp'];
    $freelvl = (int)$s['Freelvl'];
    
    // Calculate next level XP
    if ($freelvl > 0) {
        $nxlvl = 0;
    } elseif ($lvl < 249) {
        if ($lvl > 9) {
            $j1 = round(($lvl - 5) / 10);
            $j2 = $lvl - ($j1 * 10);
            $nxlvl = round(1000 * (pow(1.35, $j1) - 1) / 0.35) + round(100 * $j2 * pow(1.35, $j1));
        } else {
            $nxlvl = 100 * $lvl;
        }
    } elseif ($lvl < 999) {
        $nxlvl = 5000000 + ($lvl - 249) * 200000;
    } else {
        $nxlvl = 300000000 + ($lvl - 999) * 3700000;
    }
    
    api_success([
        'level' => $lvl,
        'exp' => $exp,
        'nextLevelExp' => $nxlvl,
        'canLevelUp' => $exp >= $nxlvl,
        'freeLevels' => $freelvl,
        'stats' => [
            'str' => (int)$s['Str'],
            'dex' => (int)$s['Dex'],
            'vit' => (int)$s['Vit'],
            'ntl' => (int)$s['Ntl'],
            'wis' => (int)$s['Wis'],
        ],
    ]);
    close_dbx();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') api_error('Method not allowed', 405);

$input = json_decode(file_get_contents('php://input'), true) ?: [];

switch ($route) {
    case 'levelup':
        $stat = $input['stat'] ?? 'balanced';
        api_success(api_levelup($pid, $stat));
        break;
    case 'train':
        api_success(api_train($pid));
        break;
    default:
        api_error("Unknown training route: $route", 404);
}

close_dbx();

function api_levelup($pid, $statChoice) {
    global $dbx;
    
    $sQ = mysqli_query($dbx, "SELECT * FROM Stats WHERE Id=$pid");
    $s = mysqli_fetch_assoc($sQ);
    
    $lvl = (int)$s['Lvl'];
    $exp = (int)$s['Exp'];
    $freelvl = (int)$s['Freelvl'];
    $race = (int)$s['Race'] % 100;
    $str = (int)$s['Str'];
    $dex = (int)$s['Dex'];
    $vit = (int)$s['Vit'];
    $ntl = (int)$s['Ntl'];
    $wis = (int)$s['Wis'];
    $clan = (int)$s['Clan'];
    
    // Calculate next level XP
    if ($freelvl > 0) {
        $nxlvl = 0;
    } elseif ($lvl < 249) {
        if ($lvl > 9) {
            $j1 = round(($lvl - 5) / 10);
            $j2 = $lvl - ($j1 * 10);
            $nxlvl = round(1000 * (pow(1.35, $j1) - 1) / 0.35) + round(100 * $j2 * pow(1.35, $j1));
        } else {
            $nxlvl = 100 * $lvl;
        }
    } elseif ($lvl < 999) {
        $nxlvl = 5000000 + ($lvl - 249) * 200000;
    } else {
        $nxlvl = 300000000 + ($lvl - 999) * 3700000;
    }
    
    if ($exp < $nxlvl && $freelvl <= 0) {
        api_error('Not enough XP to level up', 400);
    }
    
    // Get race base stats
    $rQ = mysqli_query($dbx, "SELECT R_str, R_dex, R_vit, R_ntl, R_wis FROM Races WHERE Rid=$race");
    $r = mysqli_fetch_assoc($rQ);
    $rStr = (int)$r['R_str'];
    $rDex = (int)$r['R_dex'];
    $rVit = (int)$r['R_vit'];
    $rNtl = (int)$r['R_ntl'];
    $rWis = (int)$r['R_wis'];
    
    // Apply stat gains based on choice
    $str += round($rStr * 0.5);
    $dex += round($rDex * 0.5);
    $vit += round($rVit * 0.5);
    $ntl += round($rNtl * 0.5);
    $wis += round($rWis * 0.5);
    
    switch ($statChoice) {
        case 'str':
            $str += 50;
            break;
        case 'dex':
            $dex += 50;
            break;
        case 'vit':
            $vit += 50;
            break;
        case 'ntl':
            $ntl += 50;
            break;
        case 'wis':
            $wis += 50;
            break;
        default: // balanced
            $str += round($rStr * 0.5);
            $dex += round($rDex * 0.5);
            $vit += round($rVit * 0.5);
            $ntl += round($rNtl * 0.5);
            $wis += round($rWis * 0.5);
            break;
    }
    
    $health = $vit;
    
    if ($freelvl > 0) {
        $freelvl--;
        $expRemaining = $exp; // free level doesn't consume XP
    } else {
        $expRemaining = $exp - $nxlvl;
    }
    
    $newLvl = ($str + $dex + $vit + $ntl + $wis) / 100;
    
    // Update clan power if crossing level 10 boundary
    if ($clan > 1 && (int)$newLvl % 10 == 0 && (int)$newLvl != (int)$lvl) {
        // Simplified: just add some clan power
        mysqli_query($dbx, "UPDATE Clans SET Cpower = Cpower + " . (int)$newLvl . " WHERE Cid=$clan");
    }
    
    mysqli_query($dbx, "UPDATE Stats SET Str=$str, Dex=$dex, Vit=$vit, Ntl=$ntl, Wis=$wis, Health=$health, Exp=$expRemaining, Lvl=$newLvl, Freelvl=$freelvl WHERE Id=$pid");
    
    return [
        'action' => 'levelup',
        'statChoice' => $statChoice,
        'oldLevel' => $lvl,
        'newLevel' => $newLvl,
        'stats' => [
            'str' => $str, 'dex' => $dex, 'vit' => $vit,
            'ntl' => $ntl, 'wis' => $wis,
        ],
        'health' => $health,
        'expRemaining' => $expRemaining,
        'freeLevels' => $freelvl,
    ];
}

function api_train($pid) {
    global $dbx;
    
    // Load player location
    $pQ = mysqli_query($dbx, "SELECT Loc_zone FROM Players WHERE Id=$pid");
    $p = mysqli_fetch_assoc($pQ);
    $zone = (int)$p['Loc_zone'];
    
    $zQ = mysqli_query($dbx, "SELECT zmage FROM Zones WHERE znum=$zone");
    $z = mysqli_fetch_assoc($zQ);
    $zmage = (int)$z['zmage'];
    
    if ($zmage == 0) {
        api_error('No trainer in this zone', 400);
    }
    
    $sQ = mysqli_query($dbx, "SELECT Lvl, Gold, Tstatus FROM Stats WHERE Id=$pid");
    $s = mysqli_fetch_assoc($sQ);
    $lvl = (int)$s['Lvl'];
    $gold = (int)$s['Gold'];
    $cost = $lvl * 100;
    
    if ($gold < $cost) {
        api_error("Need {$cost} gold to train (you have {$gold})", 400);
    }
    
    // Pay and show masteries
    $newGold = $gold - $cost;
    mysqli_query($dbx, "UPDATE Stats SET Gold=$newGold WHERE Id=$pid");
    
    // Load masteries
    $mQ = mysqli_query($dbx, "SELECT Sword_m, Axe_m, Staff_m, Mace_m, Fire_m, Cold_m, Air_m, Arcane_m, Armor_m, Double_m FROM Players WHERE Id=$pid");
    $m = mysqli_fetch_assoc($mQ);
    
    return [
        'action' => 'train',
        'cost' => $cost,
        'goldRemaining' => $newGold,
        'masteries' => [
            'sword'   => (int)$m['Sword_m'],
            'axe'     => (int)$m['Axe_m'],
            'staff'   => (int)$m['Staff_m'],
            'mace'    => (int)$m['Mace_m'],
            'fire'    => (int)$m['Fire_m'],
            'cold'    => (int)$m['Cold_m'],
            'air'     => (int)$m['Air_m'],
            'arcane'  => (int)$m['Arcane_m'],
            'armor'   => (int)$m['Armor_m'],
            'double'  => (int)$m['Double_m'],
        ],
    ];
}
