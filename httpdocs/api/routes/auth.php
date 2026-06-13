<?php
// /api/auth/* — login, create, validate, logout
require_once __DIR__ . '/../auth.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $rest[0] ?? '';
$body = request_body();

if ($action === 'login' && $method === 'POST') {
    $login = trim($body['login'] ?? '');
    $password = (string)($body['password'] ?? '');
    if ($login === '' || $password === '') {
        json_error('login and password are required', 422);
    }

    init_dbx();
    $player = shim_authenticate($login, $password);
    close_dbx();

    if (!$player) {
        json_error('Invalid login or password', 401);
    }

    shim_start_session($player);
    json_response([
        'token' => session_id(),
        'playerId' => (int)$player['Id'],
        'name' => $player['name'],
    ]);
}

if ($action === 'logout' && $method === 'POST') {
    $_SESSION = [];
    session_destroy();
    json_response(['loggedOut' => true]);
}

if ($action === 'create' && $method === 'POST') {
    $name = ucwords(trim($body['name'] ?? ''));
    $login = ucwords(trim($body['login'] ?? ''));
    $password = (string)($body['password'] ?? '');
    $email = trim($body['email'] ?? '');
    $race = (int)($body['race'] ?? 0);
    $gender = (int)($body['gender'] ?? 0);

    if (strlen($name) < 4 || strlen($login) < 4 || strlen($email) < 6) {
        json_error('Name, login (4+ chars) and email (6+ chars) are required', 422);
    }
    if (strlen($password) < 4 || strlen($password) > 10) {
        json_error('Password must be 4-10 characters', 422);
    }
    if ($name === $login) {
        json_error('Login and character name must be different', 422);
    }
    if (!tarkista($name) || !tarkista($login) || !tarkista($email)) {
        json_error('Invalid characters used', 422);
    }

    init_dbx();

    $nameEsc = mysqli_real_escape_string($dbx, $name);
    $loginEsc = mysqli_real_escape_string($dbx, $login);
    $emailEsc = mysqli_real_escape_string($dbx, $email);

    $query = "select Id from Players where name = '$nameEsc'";
    $result = mysqli_query($dbx, $query);
    if (mysqli_num_rows($result) > 0) {
        close_dbx();
        json_error('Character name already exists', 409);
    }
    $query = "select Id from Players where login = '$loginEsc'";
    $result = mysqli_query($dbx, $query);
    if (mysqli_num_rows($result) > 0) {
        close_dbx();
        json_error('Login already exists', 409);
    }

    $query = "select * from Races where Rid = $race";
    $result = mysqli_query($dbx, $query);
    if (mysqli_num_rows($result) !== 1) {
        close_dbx();
        json_error('Unknown race', 422);
    }
    $r = mysqli_fetch_assoc($result);
    $raceCode = $race + 100 * min(max($gender, 0), 1);

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0';

    $query = "insert into Players
        (name, password, mail, channels, race, gender, loc_x, loc_y, loc_zone, login,
         banned, pstatus, lastaction, ax_time, last_ip)
        values
        ('$nameEsc', '" . mysqli_real_escape_string($dbx, $passwordHash) . "', '$emailEsc', 0, $raceCode, $gender, 1, 1, $race, '$loginEsc',
         0, 0, 'cr', now(), '" . mysqli_real_escape_string($dbx, $remoteAddr) . "')";
    if (!mysqli_query($dbx, $query)) {
        close_dbx();
        json_error('Could not create character: ' . mysqli_error($dbx), 500);
    }
    $playerId = mysqli_insert_id($dbx);

    $health = (int)$r['R_vit'];
    $query = "insert into Stats
        (Id, name, race, str, dex, vit, ntl, wis, health, gold, banked, exp, lvl, freelvl, turns, clan)
        values
        ($playerId, '$nameEsc', $raceCode, {$r['R_str']}, {$r['R_dex']}, {$r['R_vit']}, {$r['R_ntl']}, {$r['R_wis']}, $health, {$r['R_gold']}, 0, 0, " . max((int)$r['R_level'], 1) . ", 0, 5400, 0)";
    mysqli_query($dbx, $query);

    $query = "insert into Inventory (Id, name, checked) values ($playerId, '$nameEsc', 1)";
    mysqli_query($dbx, $query);

    // Validation token mirrors validate.php: unix timestamp of ax_time set above.
    $query = "select unix_timestamp(ax_time) as ax_time from Players where Id = $playerId";
    $result = mysqli_query($dbx, $query);
    $row = mysqli_fetch_assoc($result);

    close_dbx();

    json_response([
        'playerId' => (int)$playerId,
        'name' => $name,
        'login' => $login,
        'validationToken' => $row['ax_time'],
    ]);
}

if ($action === 'validate' && $method === 'POST') {
    $userId = (int)($body['userId'] ?? 0);
    $token = (string)($body['token'] ?? '');

    if ($userId <= 0 || $token === '') {
        json_error('userId and token are required', 422);
    }

    init_dbx();
    $query = "select Id, banned, unix_timestamp(ax_time) as ax_time from Players where lastaction = 'cr' and Id = $userId and pstatus = 0";
    $result = mysqli_query($dbx, $query);
    if (!$result || mysqli_num_rows($result) !== 1) {
        close_dbx();
        json_error('Character could not be validated', 404);
    }
    $row = mysqli_fetch_assoc($result);
    if ((int)$row['banned'] === 100) {
        close_dbx();
        json_error('Character is banned', 403);
    }
    if ((string)$row['ax_time'] !== $token) {
        mysqli_query($dbx, "update Players set banned = 100 where Id = $userId");
        close_dbx();
        json_error('Invalid validation token', 403);
    }
    mysqli_query($dbx, "update Players set lastaction = 'va', ax_time = now(), pstatus = 1 where Id = $userId");
    close_dbx();

    json_response(['success' => true]);
}

json_error('Unknown auth action', 404);
