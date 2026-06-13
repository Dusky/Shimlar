<?php
/**
 * Shimlar API — Player Routes
 * GET /api/player/stats
 * GET /api/player/inventory
 * GET /api/player/profile
 * GET /api/player/messages
 */

require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../incz/constvars.inc';

init_dbx();

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') api_error('Method not allowed', 405);

$player = api_require_auth();
$pid = (int)$player['Id'];

$path = trim($_GET['route'] ?? '', '/');
$route = preg_replace('#^player/#', '', $path);

switch ($route) {
    case 'stats':
        // Load fresh stats
        $sQ = mysqli_query($dbx, "SELECT * FROM Stats WHERE Id=$pid");
        $s = mysqli_fetch_assoc($sQ);

        $rnamea = ["Human","Dwarf","Elf","Dark Elf","Giant","Troll","Goblin","Angel","Gargoyle","Balrog","Kender","Half Elf","Dark Angel","Galatai","Flame Demon","Duergar","Sprite","Genie","Dragon","Vampire"];
        $race = (int)$s['Race'] % 100;
        $raceName = isset($rnamea[$race]) ? $rnamea[$race] : 'Unknown';

        api_success([
            'name' => $s['Name'],
            'race' => $raceName,
            'raceId' => $race,
            'level' => (int)$s['Lvl'],
            'exp' => (int)$s['Exp'],
            'gold' => (int)$s['Gold'],
            'banked' => (int)$s['Banked'],
            'str' => (int)$s['Str'],
            'dex' => (int)$s['Dex'],
            'vit' => (int)$s['Vit'],
            'ntl' => (int)$s['Ntl'],
            'wis' => (int)$s['Wis'],
            'health' => (int)$s['Health'],
            'e_health' => (int)$s['E_health'],
            'freelvl' => (int)$s['Freelvl'],
            'turns' => (int)$s['Turns'],
            'clan' => (int)$s['Clan'],
            'align' => (int)$s['Align'],
            'fame' => (int)$s['Fame'],
            'pkill' => (int)$s['Pkill'],
            'opponent' => (int)$s['Opponent'],
            'status' => (int)$s['Status'],
        ]);
        break;

    case 'inventory':
        $invQ = mysqli_query($dbx, "SELECT * FROM Inventory WHERE Id = $pid");
        if (!$invQ) api_error('Inventory not found', 404);
        $inv = mysqli_fetch_assoc($invQ);

        $items = [];
        for ($i = 0; $i <= 38; $i++) {
            $key = 'I' . $i;
            $val = (int)$inv[$key];
            if ($val > 0) {
                $items['i' . $i] = $val;
            }
        }

        api_success([
            'items' => $items,
            'equipped' => [
                'lhand' => (int)$inv['Lhand'],
                'rhand' => (int)$inv['Rhand'],
                'spell1' => (int)$inv['Spellone'],
                'spell2' => (int)$inv['Spelltwo'],
                'armor' => 0,
            ],
            'accessory' => (int)$inv['Accx'],
            'checked' => (int)$inv['Checked'],
        ]);
        break;

    case 'profile':
        $pQ = mysqli_query($dbx, "SELECT * FROM Players WHERE Id=$pid");
        $p = mysqli_fetch_assoc($pQ);
        $sQ = mysqli_query($dbx, "SELECT * FROM Stats WHERE Id=$pid");
        $s = mysqli_fetch_assoc($sQ);

        $rnamea = ["Human","Dwarf","Elf","Dark Elf","Giant","Troll","Goblin","Angel","Gargoyle","Balrog","Kender","Half Elf","Dark Angel","Galatai","Flame Demon","Duergar","Sprite","Genie","Dragon","Vampire"];
        $race = (int)$s['Race'] % 100;
        $raceName = isset($rnamea[$race]) ? $rnamea[$race] : 'Unknown';

        // Get clan name
        $clanName = '';
        $clanId = (int)$s['Clan'];
        if ($clanId > 0) {
            $clanResult = mysqli_query($dbx, "SELECT cname FROM Clans WHERE cid=$clanId");
            if ($clanResult && mysqli_num_rows($clanResult) > 0) {
                $crow = mysqli_fetch_row($clanResult);
                $clanName = $crow[0];
            }
        }

        api_success([
            'id' => $pid,
            'name' => $p['Name'],
            'race' => $raceName,
            'level' => (int)$s['Lvl'],
            'channels' => (int)$p['Channels'],
            'banned' => (int)$p['Banned'],
            'location' => [
                'x' => (int)$p['Loc_x'],
                'y' => (int)$p['Loc_y'],
                'zone' => (int)$p['Loc_zone'],
            ],
            'clan' => [
                'id' => $clanId,
                'name' => $clanName,
            ],
            'masteries' => [
                'sword' => (int)($p['Sword_m'] ?? 0),
                'axe' => (int)($p['Axe_m'] ?? 0),
                'staff' => (int)($p['Staff_m'] ?? 0),
                'mace' => (int)($p['Mace_m'] ?? 0),
                'armor' => (int)($p['Armor_m'] ?? 0),
                'fire' => (int)($p['Fire_m'] ?? 0),
                'cold' => (int)($p['Cold_m'] ?? 0),
                'air' => (int)($p['Air_m'] ?? 0),
                'arcane' => (int)($p['Arcane_m'] ?? 0),
                'double' => (int)($p['Double_m'] ?? 0),
            ],
        ]);
        break;

    case 'messages':
        $msgQ = mysqli_query($dbx, "SELECT * FROM Messages WHERE Id = $pid ORDER BY Ts DESC LIMIT 50");
        $messages = [];
        if ($msgQ) {
            while ($row = mysqli_fetch_assoc($msgQ)) {
                $messages[] = [
                    'sender' => $row['Sender'] ?? $row['sender'] ?? '',
                    'text' => $row['Msg'] ?? $row['msg'] ?? '',
                    'type' => (int)($row['Msgtype'] ?? $row['msgtype'] ?? 0),
                    'time' => $row['Ts'] ?? $row['ts'] ?? null,
                ];
            }
        }
        api_success(['messages' => $messages]);
        break;

    default:
        api_error("Unknown player route: $route", 404);
}
