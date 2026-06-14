<?php
/**
 * Shimlar API — Quest Routes
 * GET  /api/quest/list     — available quests in current zone
 * POST /api/quest/accept   — accept a quest
 * POST /api/quest/complete — turn in completed quest
 * GET  /api/quest/current  — show current active quest
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
$route = preg_replace('#^quest/#', '', $path);

// Check for Quests table columns
function api_quests_table_exists() {
    global $dbx;
    $r = mysqli_query($dbx, "SHOW TABLES LIKE 'Quests'");
    return $r && mysqli_num_rows($r) > 0;
}

if (!api_quests_table_exists()) {
    global $dbx;
    mysqli_query($dbx, "CREATE TABLE IF NOT EXISTS Quests (
        Qmon INT DEFAULT 0,
        Mlist TEXT DEFAULT NULL,
        ZoneId INT DEFAULT 0,
        Qexp BIGINT DEFAULT 0,
        Qgold BIGINT DEFAULT 0,
        Qitem BIGINT DEFAULT 0,
        Qstatus VARCHAR(10) DEFAULT 'x',
        Qnum INT AUTO_INCREMENT PRIMARY KEY,
        Qmaxlvl INT DEFAULT 0,
        Qlife INT DEFAULT 0,
        Qminlvl INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Ensure Players table has quest columns
function api_ensure_quest_columns() {
    global $dbx;
    $check = mysqli_query($dbx, "SHOW COLUMNS FROM Players LIKE 'Qhd'");
    if (mysqli_num_rows($check) == 0) {
        mysqli_query($dbx, "ALTER TABLE Players ADD COLUMN Qhd INT DEFAULT 0");
        mysqli_query($dbx, "ALTER TABLE Players ADD COLUMN Quests INT DEFAULT 0");
        mysqli_query($dbx, "ALTER TABLE Players ADD COLUMN Qnum INT DEFAULT 0");
    }
}
api_ensure_quest_columns();

// Ensure Stats has Quests column  
$pqCheck = mysqli_query($dbx, "SHOW COLUMNS FROM Stats LIKE 'qnum'");
if ($pqCheck && mysqli_num_rows($pqCheck) == 0) {
    @mysqli_query($dbx, "ALTER TABLE Stats ADD COLUMN Qnum INT DEFAULT 0");
}

switch ($route) {
    case 'list':
        api_success(api_quest_list($pid));
        break;
    case 'accept':
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $qnum = (int)($input['questId'] ?? 0);
        api_success(api_quest_accept($pid, $qnum));
        break;
    case 'complete':
        api_success(api_quest_complete($pid));
        break;
    case 'current':
        api_success(api_quest_current($pid));
        break;
    default:
        api_error("Unknown quest route: $route", 404);
}

close_dbx();

function api_quest_list($pid) {
    global $dbx;
    
    // Get available quests (not yet accepted, within level range)
    $sQ = mysqli_query($dbx, "SELECT Lvl FROM Stats WHERE Id=$pid");
    $s = mysqli_fetch_assoc($sQ);
    $lvl = (int)$s['Lvl'];
    
    $qQ = mysqli_query($dbx, "SELECT * FROM Quests WHERE Qlevel <= $lvl+200 ORDER BY Qnum DESC LIMIT 10");
    
    $quests = [];
    while ($q = mysqli_fetch_assoc($qQ)) {
        $quests[] = [
            'id'      => (int)$q['Qnum'],
            'zone'    => (int)$q['Qzone'],
            'monster' => (int)$q['Qmon'],
            'expReward'   => (int)$q['Qexp'],
            'goldReward'  => (int)$q['Qgold'],
            'itemReward'  => (int)$q['Qitem'],
            'minLevel' => (int)$q['Qlevel'],
            'maxLevel' => (int)$q['Qlevel'] + 200,
        ];
    }
    
    return ['quests' => $quests, 'playerLevel' => $lvl];
}

function api_quest_accept($pid, $qnum) {
    global $dbx;
    
    if ($qnum <= 0) api_error('Invalid quest ID', 400);
    
    // Check player doesn't already have a quest
    $pQ = mysqli_query($dbx, "SELECT Qnum FROM Players WHERE Id=$pid");
    $p = mysqli_fetch_assoc($pQ);
    if ((int)$p['Qnum'] > 0) {
        api_error('Already have an active quest. Complete it first.', 400);
    }
    
    // Get quest details
    $qQ = mysqli_query($dbx, "SELECT * FROM Quests WHERE Qnum=$qnum");
    if (!$qQ || mysqli_num_rows($qQ) == 0) api_error('Quest not found', 404);
    $q = mysqli_fetch_assoc($qQ);
    
    // Check level requirement
    $sQ = mysqli_query($dbx, "SELECT Lvl FROM Stats WHERE Id=$pid");
    $s = mysqli_fetch_assoc($sQ);
    $lvl = (int)$s['Lvl'];
    
    if ($lvl < (int)$q['Qlevel']) api_error('Level too low for this quest', 400);
    if ($lvl > (int)$q['Qlevel'] + 200) api_error('Level too high for this quest', 400);
    
    // Accept quest
    mysqli_query($dbx, "UPDATE Players SET Qnum=$qnum WHERE Id=$pid");
    
    return [
        'action' => 'accept',
        'quest' => [
            'id'      => (int)$q['Qnum'],
            'zone'    => (int)$q['Qzone'],
            'monster' => (int)$q['Qmon'],
            'expReward'   => (int)$q['Qexp'],
            'goldReward'  => (int)$q['Qgold'],
            'itemReward'  => (int)$q['Qitem'],
        ],
    ];
}

function api_quest_complete($pid) {
    global $dbx;
    
    $pQ = mysqli_query($dbx, "SELECT Qnum FROM Players WHERE Id=$pid");
    $p = mysqli_fetch_assoc($pQ);
    $qnum = (int)$p['Qnum'];
    
    if ($qnum <= 0) api_error('No active quest to complete', 400);
    
    $qQ = mysqli_query($dbx, "SELECT * FROM Quests WHERE Qnum=$qnum");
    if (!$qQ || mysqli_num_rows($qQ) == 0) api_error('Quest no longer available', 404);
    $q = mysqli_fetch_assoc($qQ);
    
    // Find empty inventory slot for item reward
    $iQ = mysqli_query($dbx, "SELECT I0,I1,I2,I3,I4,I5,I6,I7,I8,I9,I10,I11,I12,I13,I14,I15,I16,I17,I18,I19,I20,I21,I22,I23,I24,I25,I26,I27,I28,I29 FROM Inventory WHERE Id=$pid");
    $inv = mysqli_fetch_assoc($iQ);
    $emptySlot = -1;
    for ($i = 0; $i < 30; $i++) {
        if ((int)$inv['I' . $i] == 0) { $emptySlot = $i; break; }
    }
    
    $qitem = (int)$q['Qitem'];
    if ($qitem > 0 && $emptySlot == -1) {
        api_error('Inventory full — cannot complete quest', 400);
    }
    
    // Give rewards
    $qexp = (int)$q['Qexp'];
    $qgold = (int)$q['Qgold'];
    
    mysqli_query($dbx, "UPDATE Stats SET Gold=Gold+$qgold, Exp=Exp+$qexp WHERE Id=$pid");
    mysqli_query($dbx, "UPDATE Players SET Quests=Quests+1, Qnum=0 WHERE Id=$pid");
    
    if ($qitem > 0) {
        $col = 'I' . $emptySlot;
        mysqli_query($dbx, "UPDATE Inventory SET $col=$qitem WHERE Id=$pid");
    }
    
    // Delete the quest
    mysqli_query($dbx, "DELETE FROM Quests WHERE Qnum=$qnum");
    
    return [
        'action' => 'complete',
        'rewards' => [
            'exp' => $qexp,
            'gold' => $qgold,
            'item' => $qitem > 0 ? ['id' => $qitem, 'slot' => $emptySlot] : null,
        ],
    ];
}

function api_quest_current($pid) {
    global $dbx;
    
    $pQ = mysqli_query($dbx, "SELECT Qnum FROM Players WHERE Id=$pid");
    $p = mysqli_fetch_assoc($pQ);
    $qnum = (int)$p['Qnum'];
    
    if ($qnum <= 0) {
        return ['activeQuest' => false];
    }
    
    $qQ = mysqli_query($dbx, "SELECT * FROM Quests WHERE Qnum=$qnum");
    if (!$qQ || mysqli_num_rows($qQ) == 0) {
        // Quest expired
        mysqli_query($dbx, "UPDATE Players SET Qnum=0 WHERE Id=$pid");
        return ['activeQuest' => false, 'message' => 'Quest expired'];
    }
    $q = mysqli_fetch_assoc($qQ);
    
    return [
        'activeQuest' => true,
        'quest' => [
            'id'      => (int)$q['Qnum'],
            'zone'    => (int)$q['Qzone'],
            'monster' => (int)$q['Qmon'],
            'expReward'   => (int)$q['Qexp'],
            'goldReward'  => (int)$q['Qgold'],
            'itemReward'  => (int)$q['Qitem'],
        ],
    ];
}
