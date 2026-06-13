<?php
/**
 * Shimlar API — Clan Routes
 * GET  /api/clan/info
 * POST /api/clan/create
 * POST /api/clan/join
 * POST /api/clan/leave
 * POST /api/clan/donate
 */

require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../incz/constvars.inc';

init_dbx();

$player = api_require_auth();
$pid = (int)$player['Id'];
$clanId = (int)($player['clan'] ?? 0);

$path = trim($_GET['route'] ?? '', '/');
$route = preg_replace('#^clan/#', '', $path);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if ($route === 'info') {
            if ($clanId <= 0) {
                api_success(['clan' => null, 'message' => 'Not in a clan']);
            }

            $cQ = mysqli_query($dbx, "SELECT * FROM Clans WHERE cid=$clanId");
            if (!$cQ || mysqli_num_rows($cQ) === 0) api_error('Clan not found', 404);
            $clan = mysqli_fetch_assoc($cQ);

            // Get member count
            $mQ = mysqli_query($dbx, "SELECT COUNT(*) as cnt FROM Stats WHERE clan=$clanId");
            $members = mysqli_fetch_assoc($mQ);

            api_success([
                'clan' => [
                    'id' => (int)$clan['cid'],
                    'name' => $clan['cname'],
                    'leader' => $clan['cleader'],
                    'leader2' => $clan['cleader2'],
                    'power' => (int)$clan['cpower'],
                    'bonus' => (int)$clan['cbonus'],
                    'duels' => (int)$clan['cduels'],
                    'gold' => (int)($clan['cgold'] ?? 0),
                    'memberCount' => (int)$members['cnt'],
                ]
            ]);
        }

        if ($route === 'list') {
            $q = mysqli_query($dbx, "SELECT c.*, (SELECT COUNT(*) FROM Stats s WHERE s.clan=c.cid) as members FROM Clans c ORDER BY c.cpower DESC");
            $clans = [];
            while ($row = mysqli_fetch_assoc($q)) {
                $clans[] = [
                    'id' => (int)$row['cid'],
                    'name' => $row['cname'],
                    'leader' => $row['cleader'],
                    'power' => (int)$row['cpower'],
                    'members' => (int)$row['members'],
                ];
            }
            api_success(['clans' => $clans]);
        }
        break;

    case 'POST':
        switch ($route) {
            case 'create':
                $name = trim(api_get('name', ''));
                if (strlen($name) < 3) api_error('Clan name too short', 400);
                if ($clanId > 0) api_error('Already in a clan', 400);

                $safe = mysqli_real_escape_string($dbx, $name);
                $check = mysqli_query($dbx, "SELECT cid FROM Clans WHERE cname='$safe'");
                if (mysqli_num_rows($check) > 0) api_error('Clan name taken', 409);

                $pname = mysqli_real_escape_string($dbx, $player['Name']);
                $cpower = (int)($player['lvl'] ?? 1);

                mysqli_query($dbx, "INSERT INTO Clans (cname, cint, cint2, cleader, cleader2, cpower, cbonus, cduels, cturn, cgold) 
                    VALUES ('$safe', $pid, NULL, '$pname', '', $cpower, 0, 0, 0, 0)");
                $newClanId = mysqli_insert_id($dbx);

                mysqli_query($dbx, "UPDATE Stats SET clan=$newClanId WHERE Id=$pid");

                api_success(['clanId' => $newClanId, 'name' => $name], 'Clan created');
                break;

            case 'leave':
                if ($clanId <= 0) api_error('Not in a clan', 400);

                // Check if leader
                $cQ = mysqli_query($dbx, "SELECT cleader, cint FROM Clans WHERE cid=$clanId");
                $clan = mysqli_fetch_assoc($cQ);

                if ($clan['cint'] == $pid) {
                    // Leader leaving — transfer or dissolve
                    if ($clan['cint']) {
                        // Transfer to co-leader
                        $coLeader = (int)$clan['cint'];
                        mysqli_query($dbx, "UPDATE Clans SET cint=$coLeader, cleader=cleader2, cleader2='' WHERE cid=$clanId");
                    }
                }

                mysqli_query($dbx, "UPDATE Stats SET clan=0 WHERE Id=$pid");
                api_success(null, 'Left clan');
                break;

            case 'donate':
                $amount = (int)api_get('amount', 0);
                if ($amount <= 0) api_error('Amount must be positive', 400);
                if ($clanId <= 0) api_error('Not in a clan', 400);

                $statsQ = mysqli_query($dbx, "SELECT banked FROM Stats WHERE Id=$pid");
                $stats = mysqli_fetch_assoc($statsQ);
                if ($amount > (int)$stats['banked']) api_error('Not enough banked gold', 400);

                mysqli_query($dbx, "UPDATE Stats SET banked=banked-$amount WHERE Id=$pid");
                mysqli_query($dbx, "UPDATE Clans SET cgold=cgold+$amount WHERE cid=$clanId");

                api_success(['donated' => $amount], 'Donated to clan');
                break;

            default:
                api_error("Unknown clan route: $route", 404);
        }
        break;

    default:
        api_error('Method not allowed', 405);
}

close_dbx();
