<?php
/**
 * Shimlar API — Session & Auth Helpers
 */

function api_session_start() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Authenticate player by login + password.
 * Returns player info array or false.
 */
function api_authenticate($login, $password) {
    global $dbx;
    $login = trim($login);
    if (empty($login) || empty($password)) return false;

    // Try login field first
    $pid = getIdByLogin($login);
    if ($pid == -1) {
        // Try as numeric ID
        if (is_numeric($login)) {
            $pid = (int)$login;
        } else {
            return false;
        }
    }

    $query = "SELECT p.Id, p.Name, p.Password, p.Channels, p.Banned, p.Pstatus, p.Loc_x, p.Loc_y, p.Loc_zone 
              FROM Players p WHERE p.Id = $pid";
    $result = mysqli_query($dbx, $query);
    if (!$result || mysqli_num_rows($result) !== 1) return false;

    $player = mysqli_fetch_assoc($result);

    // Password check (support both plaintext legacy and hashed)
    if (!password_verify($password, $player['Password']) && $player['Password'] !== $password) {
        return false;
    }

    // Check bans
    if ($player['Banned'] == 100) return ['error' => 'banned'];
    if ($player['Pstatus'] == 0) return ['error' => 'unvalidated'];

    return $player;
}

/**
 * Set session for authenticated player
 */
function api_login_session($player) {
    api_session_start();
    $_SESSION['player_id'] = (int)$player['Id'];
    $_SESSION['player_name'] = $player['Name'];
    $_SESSION['logged_in'] = true;
}

/**
 * Check if current session is authenticated.
 * Returns player array or sends 401 and exits.
 */
function api_require_auth() {
    global $dbx;
    api_session_start();

    if (empty($_SESSION['logged_in']) || empty($_SESSION['player_id'])) {
        api_json(['error' => 'Not authenticated'], 401);
        exit;
    }

    $pid = (int)$_SESSION['player_id'];
    $query = "SELECT p.*, s.* FROM Players p 
              LEFT JOIN Stats s ON p.Id = s.Id 
              WHERE p.Id = $pid";
    $result = mysqli_query($dbx, $query);
    if (!$result || mysqli_num_rows($result) !== 1) {
        api_json(['error' => 'Player not found'], 404);
        exit;
    }

    return mysqli_fetch_assoc($result);
}

/**
 * Get player ID from session without full load
 */
function api_player_id() {
    api_session_start();
    return !empty($_SESSION['player_id']) ? (int)$_SESSION['player_id'] : 0;
}
