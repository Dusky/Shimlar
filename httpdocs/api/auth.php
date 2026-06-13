<?php
// Session + password helpers shared by the API.
// Players.password is plaintext on legacy accounts; new accounts created via
// /api/auth/create are hashed with password_hash(). Both are accepted here.

require_once __DIR__ . '/../../incz/constvars.inc';

function shim_password_matches($inputPassword, $storedPassword) {
    if ($storedPassword !== '' && (strpos($storedPassword, '$2y$') === 0 || strpos($storedPassword, '$2a$') === 0)) {
        return password_verify($inputPassword, $storedPassword);
    }
    return $inputPassword === $storedPassword;
}

// Looks up a player by login name and verifies the password.
// Returns the Players row (assoc array) on success, or null on failure.
function shim_authenticate($login, $password) {
    global $dbx;
    $login = mysqli_real_escape_string($dbx, $login);
    $query = "select Id, name, password, banned, pstatus from Players where login = '$login'";
    $result = mysqli_query($dbx, $query);
    if (!$result || mysqli_num_rows($result) !== 1) {
        return null;
    }
    $row = mysqli_fetch_assoc($result);
    if ((int)$row['banned'] === 100 || (int)$row['pstatus'] === 0) {
        return null;
    }
    if (!shim_password_matches($password, $row['password'])) {
        return null;
    }
    return $row;
}

// Starts an authenticated session for the given player row.
function shim_start_session($playerRow) {
    session_regenerate_id(true);
    $_SESSION['player_id'] = (int)$playerRow['Id'];
    $_SESSION['player_name'] = $playerRow['name'];
    // Stored verbatim so legacy game logic (which still does `$p == $password`
    // against the DB column) can be satisfied without re-prompting for a password.
    $_SESSION['db_password'] = $playerRow['password'];
}
