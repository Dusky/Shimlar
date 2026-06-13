<?php
/**
 * Shimlar API — Auth Routes
 * POST /api/auth/login
 * POST /api/auth/logout
 * POST /api/auth/create
 * POST /api/auth/validate
 */

require_once __DIR__ . '/../middleware.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../../../incz/constvars.inc';

init_dbx();

$method = $_SERVER['REQUEST_METHOD'];
$path = trim($_GET['route'] ?? '', '/');
$route = preg_replace('#^auth/#', '', $path);

if ($method === 'POST') {
    switch ($route) {
        case 'login':
            $login = api_get('login');
            $password = api_get('password');

            if (!$login || !$password) {
                api_error('Login and password required', 400);
            }

            $player = api_authenticate($login, $password);

            if (!$player) {
                api_error('Invalid credentials', 401);
            }

            if (isset($player['error'])) {
                if ($player['error'] === 'banned') {
                    api_error('Account is banned', 403);
                }
                if ($player['error'] === 'unvalidated') {
                    api_error('Account not validated', 403);
                }
            }

            api_login_session($player);

            // Update last action
            $pid = (int)$player['Id'];
            mysqli_query($dbx, "UPDATE Players SET lastaction='api_login', ax_time=NOW(), wrong_pw=0 WHERE Id=$pid");

            api_success([
                'playerId' => (int)$player['Id'],
                'name' => $player['Name'],
                'channels' => (int)$player['Channels'],
                'location' => [
                    'x' => (int)$player['Loc_x'],
                    'y' => (int)$player['Loc_y'],
                    'zone' => (int)$player['Loc_zone']
                ]
            ], 'Login successful');
            break;

        case 'logout':
            api_session_start();
            session_destroy();
            api_success(null, 'Logged out');
            break;

        case 'create':
            $newpl = api_get('name');
            $newlogin = api_get('login');
            $newpw = api_get('password');
            $maili = api_get('email');
            $race = (int)api_get('race', 1);
            $gender = (int)api_get('gender', 0);

            if (strlen($newpl) < 4) api_error('Name too short (min 4 chars)', 400);
            if (strlen($newlogin) < 4) api_error('Login too short (min 4 chars)', 400);
            if (strlen($newpw) < 3) api_error('Password too short', 400);
            if (strlen($maili) < 7) api_error('Valid email required', 400);
            if ($race < 1 || $race > 10) api_error('Invalid race (1-10)', 400);

            // Check name uniqueness
            $safe_name = mysqli_real_escape_string($dbx, ucwords(trim($newpl)));
            $safe_login = mysqli_real_escape_string($dbx, trim($newlogin));
            $safe_email = mysqli_real_escape_string($dbx, str_replace('www.', '', $maili));
            $safe_pw = password_hash($newpw, PASSWORD_DEFAULT);

            $check = mysqli_query($dbx, "SELECT Id FROM Players WHERE Name='$safe_name' OR Login='$safe_login'");
            if (mysqli_num_rows($check) > 0) {
                api_error('Character name or login already taken', 409);
            }

            // Get race stats
            $raceResult = mysqli_query($dbx, "SELECT * FROM Races WHERE Rid=$race");
            if (!$raceResult || mysqli_num_rows($raceResult) === 0) {
                api_error('Race not found', 404);
            }
            extract(mysqli_fetch_assoc($raceResult));

            $rt = $race + 100 * min($gender, 1);
            $zone = $race;

            // Create player (explicit columns to match schema)
            mysqli_query($dbx, "INSERT INTO Players (Name,Password,Mail,Pic,Channels,Race,Gender,Zone,Channel,Sword,Axe,Staff,Mace,Armor,Fire,Cold,Air,Arcane,Sword_bonus,Cold_bonus,Air_bonus,Arcane_bonus,Sword_m,Axe_m,Staff_m,Mace_m,Armor_m,Fire_m,Cold_m,Air_m,Arcane_m,Double_m,Lastaction,Ax_time,Cr,Cr_time,Online,Ign,Banned,Mhd,Qhd,Jhd,Last_ip,Wrong_pw,Loc_x,Loc_y,Loc_zone,Login,Lastchat,Lastrole,Lastsale,Lastclan,Quests,Qnum,Pstatus,Ignlvl) VALUES 
                ('$safe_name','$safe_pw','$safe_email','',1,1,$gender,$zone,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,NOW(),NOW(),'cr',NOW(),1,1,0,0,0,0,'{$_SERVER['REMOTE_ADDR']}',0,1,1,$zone,'$safe_login',0,0,0,0,0,0,1,0)");

            $userid = mysqli_insert_id($dbx);

            // Create stats (explicit columns)
            mysqli_query($dbx, "INSERT INTO Stats (Id,Name,Pkill,Race,Str,Dex,Vit,Ntl,Wis,Health,Gold,Banked,Exp,Lvl,Freelvl,Opponent,Status,Turns,Clan,E_health,Fight_time,Align,Fame,Pk_time,StopSales,StopRP,Lastheal,Lasttrain,Tef,Tfc,Lastmon) VALUES 
                ($userid,'$safe_name',0,$rt,$R_str,$R_dex,$R_vit,$R_ntl,$R_wis,$R_vit,0,100,0,0,0,0,1,5400,0,0,NOW(),0,0,NOW(),0,0,NOW(),NOW(),0,0,0)");

            // Create inventory (explicit columns)
            mysqli_query($dbx, "INSERT INTO Inventory (Id,Name,I0,I1,I2,I3,I4,I5,I6,I7,I8,I9,I10,I11,I12,I13,I14,I15,I16,I17,I18,I19,I20,I21,I22,I23,I24,I25,I26,I27,I28,I29,I30,I31,I32,I33,I34,I35,I36,I37,I38,Checked,Lhand,Rhand,Spellone,Spelltwo,Accx,Tradex) VALUES 
                ($userid,'$safe_name',1000000,5000000,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1,2,0,0,0,0,0)");

            // Auto-validate for API accounts (no email needed)
            mysqli_query($dbx, "UPDATE Players SET pstatus=1 WHERE Id=$userid");

            api_success(['playerId' => $userid, 'name' => $safe_name], 'Character created');
            break;

        case 'validate':
            // Not needed for API accounts (auto-validated), but kept for compatibility
            api_success(null, 'Validation not required for API accounts');
            break;

        default:
            api_error("Unknown auth route: $route", 404);
    }
} else {
    api_error('Method not allowed', 405);
}

close_dbx();
