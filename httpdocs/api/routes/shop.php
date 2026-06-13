<?php
/**
 * Shimlar API — Shop & Item Routes
 * POST /api/shop/browse   — list items available in current zone's shop
 * POST /api/shop/buy      — buy item (c1=category, c2=item_class)
 * POST /api/shop/sell     — sell inventory item (slot=index 0-29)
 * POST /api/item/equip    — equip item (slot=index 0-29)
 * POST /api/item/unequip  — unequip slot (rhand/lhand/spellone/spelltwo/armor/accx)
 * POST /api/item/combine  — combine gem with item (item_slot, gem_slot)
 * POST /api/item/use      — use item (potions etc)
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
$route = preg_replace('#^shop/#', '', $path);
$route = preg_replace('#^item/#', '', $route);

// Load player + stats + inventory + zone in one go
$player = api_load_full_player($pid);
if (!$player) api_error('Player not found', 404);

switch ($route) {
    case 'browse':
        api_success(api_shop_browse($player));
        break;
    case 'buy':
        $cat = (int)($_POST['category'] ?? (int)(json_decode(file_get_contents('php://input'), true)['category'] ?? 0));
        $cls = (int)($_POST['class'] ?? (int)(json_decode(file_get_contents('php://input'), true)['class'] ?? 0));
        api_success(api_shop_buy($player, $cat, $cls));
        break;
    case 'sell':
        $slot = (int)($_POST['slot'] ?? (int)(json_decode(file_get_contents('php://input'), true)['slot'] ?? -1));
        api_success(api_shop_sell($player, $slot));
        break;
    case 'equip':
        $slot = (int)($_POST['slot'] ?? (int)(json_decode(file_get_contents('php://input'), true)['slot'] ?? -1));
        api_success(api_item_equip($player, $slot));
        break;
    case 'unequip':
        $slotName = $_POST['slot_name'] ?? (json_decode(file_get_contents('php://input'), true)['slot_name'] ?? '');
        api_success(api_item_unequip($player, $slotName));
        break;
    case 'combine':
        $itemSlot = (int)($_POST['item_slot'] ?? (int)(json_decode(file_get_contents('php://input'), true)['item_slot'] ?? -1));
        $gemSlot  = (int)($_POST['gem_slot'] ?? (int)(json_decode(file_get_contents('php://input'), true)['gem_slot'] ?? -1));
        api_success(api_item_combine($player, $itemSlot, $gemSlot));
        break;
    default:
        api_error("Unknown shop/item route: $route", 404);
}

close_dbx();


// ===== Functions =====

function api_load_full_player($pid) {
    global $dbx;
    
    $pQ = mysqli_query($dbx, "SELECT * FROM Players WHERE Id=$pid");
    if (!$pQ || mysqli_num_rows($pQ) !== 1) return null;
    $player = mysqli_fetch_assoc($pQ);
    
    $sQ = mysqli_query($dbx, "SELECT * FROM Stats WHERE Id=$pid");
    $player['stats'] = $sQ ? mysqli_fetch_assoc($sQ) : [];
    
    $iQ = mysqli_query($dbx, "SELECT * FROM Inventory WHERE Id=$pid");
    $inv = $iQ ? mysqli_fetch_assoc($iQ) : [];
    
    // Build inventory list (I0-I29 are item slots)
    $player['inventory'] = [];
    for ($i = 0; $i < 30; $i++) {
        $key = 'I' . $i;
        $player['inventory'][$i] = (int)($inv[$key] ?? 0);
    }
    $player['equipped'] = [
        'rhand'    => (int)($inv['Rhand'] ?? 0),
        'lhand'    => (int)($inv['Lhand'] ?? 0),
        'spellone' => (int)($inv['Spellone'] ?? 0),
        'spelltwo' => (int)($inv['Spelltwo'] ?? 0),
        'armor'    => (int)($inv['Checked'] ?? 0), // Checked is armor slot? No...
    ];
    // Actually the equipment slots are stored as inventory indices (1-30)
    $player['equipped'] = [
        'rhand'    => (int)($inv['Lhand'] ?? 0),
        'lhand'    => (int)($inv['Rhand'] ?? 0),
        'spellone' => (int)($inv['Spellone'] ?? 0),
        'spelltwo' => (int)($inv['Spelltwo'] ?? 0),
        'armor'    => 0, // computed below
        'accx'     => (int)($inv['Accx'] ?? 0),
        'tradex'   => (int)($inv['Tradex'] ?? 0),
    ];
    // Load zone info (location is in Players table)
    $zone = (int)$player['Loc_zone'];
    $zQ = mysqli_query($dbx, "SELECT * FROM Zones WHERE znum=$zone");
    $player['zone'] = $zQ ? mysqli_fetch_assoc($zQ) : [];
    
    return $player;
}

/**
 * Get item type from item number.
 * Uses original Shimlar encoding: type = floor(item / 1000000)
 */
function api_ityper($item) {
    if ($item < 100) return 0;
    return (int)(($item - ($item % 1000000)) / 1000000);
}

/**
 * Get item class/number from item number.
 * class = ((item % 1000000) - (item % 10000)) / 10000
 */
function api_inumer($item) {
    if ($item < 100) return 0;
    return (int)((($item % 1000000) - ($item % 10000)) / 10000);
}

/**
 * Get item gem value (last 2 digits)
 */
function api_igem($item) {
    return $item % 100;
}

/**
 * Get full item info as structured data
 */
function api_item_info($item) {
    if ($item == 0) return null;
    
    $type = api_ityper($item);
    $num = api_inumer($item);
    $gem = api_igem($item);
    
    $categories = [
        0 => 'Gem',
        1 => 'Sword', 2 => 'Axe', 3 => 'Staff', 4 => 'Mace',
        5 => 'Fire Spell', 6 => 'Cold Spell', 7 => 'Air Spell', 8 => 'Arcane Spell',
        9 => 'Armor', 10 => 'Shield',
    ];
    
    $catName = $categories[$type] ?? "Type $type";
    
    return [
        'id'       => $item,
        'type'     => $type,
        'category' => $catName,
        'class'    => $num,
        'gem'      => $gem,
        'isWeapon'   => ($type % 10 >= 1 && $type % 10 <= 4 && $type < 41),
        'isSpell'    => ($type % 10 >= 5 && $type % 10 <= 8 && $type < 41),
        'isArmor'    => ($type % 10 == 9),
        'isShield'   => ($type % 10 == 0 && $type > 0 && $type < 20),
        'isAccessory'=> ($type == 41),
    ];
}

/**
 * Calculate item price (buy)
 */
function api_item_price_buy($class) {
    return (int)round(50 * pow(1.7, $class));
}

/**
 * Calculate item price (sell)
 */
function api_item_price_sell($item, $tstatus = 0) {
    $type = api_ityper($item);
    $num = api_inumer($item);
    $num = max($num, 0);
    
    if ($item < 10000 && $item > 99) {
        // gems
        $price = round((($item % 100) + 12) / 25) * 10000;
        if ($tstatus == 1) $price *= 3;
        return (int)$price;
    }
    if ($item <= 99 && $item > 0) {
        // raw gem
        $price = round((($item % 100) + 12) / 25) * 10000;
        if ($tstatus == 1) $price *= 3;
        return (int)$price;
    }
    
    if ($type <= 10) {
        // normalize high values
        switch ($num) {
            case 41: $num = 36; break;
            case 38: $num = 35; break;
            case 37: $num = 34; break;
            case 36: $num = 33; break;
        }
        $num = min($num, 36);
        return (int)round(25 * pow(1.7, $num));
    } else if ($type == 41) {
        return $tstatus == 1 ? 15000000 : 5000000;
    } else if ($type == 42) {
        return 5000000;
    } else if ($type > 20) {
        return 50000 * ($num + 1);
    } else if ($type > 10) {
        return 5000 * ($num + 1);
    }
    return 0;
}

function api_shop_browse($player) {
    $zone = $player['zone'];
    $zshop = (int)($zone['zshop'] ?? 0);
    
    if ($zshop == 0) {
        return ['available' => false, 'message' => 'No shop in this zone'];
    }
    
    $maxWep = (int)($zone['max_wep'] ?? 5);
    $maxEq = (int)($zone['max_eq'] ?? 5);
    $maxSpells = (int)($zone['max_spells'] ?? 5);
    
    $items = [];
    
    // Weapons (categories 1-4: Sword, Axe, Staff, Mace)
    for ($cat = 1; $cat <= 4; $cat++) {
        for ($cls = 0; $cls < $maxWep; $cls++) {
            $price = api_item_price_buy($cls);
            $items[] = [
                'category' => $cat,
                'categoryName' => ['Sword', 'Axe', 'Staff', 'Mace'][$cat - 1],
                'class' => $cls,
                'price' => $price,
            ];
        }
    }
    
    // Spells (categories 5-8: Fire, Cold, Air, Arcane)
    for ($cat = 5; $cat <= 8; $cat++) {
        for ($cls = 0; $cls < $maxSpells; $cls++) {
            $price = api_item_price_buy($cls);
            $items[] = [
                'category' => $cat,
                'categoryName' => ['Fire', 'Cold', 'Air', 'Arcane'][$cat - 5] . ' Spell',
                'class' => $cls,
                'price' => $price,
            ];
        }
    }
    
    // Armor/Shields (categories 9-10)
    for ($cat = 9; $cat <= 10; $cat++) {
        for ($cls = 0; $cls < $maxEq; $cls++) {
            $price = api_item_price_buy($cls);
            $items[] = [
                'category' => $cat,
                'categoryName' => $cat == 9 ? 'Armor' : 'Shield',
                'class' => $cls,
                'price' => $price,
            ];
        }
    }
    
    return [
        'available' => true,
        'zone' => (int)$zone['znum'],
        'items' => $items,
        'gold' => (int)$player['stats']['Gold'],
    ];
}

function api_shop_buy($player, $cat, $cls) {
    global $dbx;
    $pid = (int)$player['Id'];
    
    $zone = $player['zone'];
    $zshop = (int)($zone['zshop'] ?? 0);
    if ($zshop == 0) api_error('No shop in this zone', 400);
    
    // Validate category
    if (!(($cat >= 1 && $cat <= 10) || $cat == 41)) {
        api_error('Invalid category', 400);
    }
    
    $maxWep = (int)($zone['max_wep'] ?? 5);
    $maxEq = (int)($zone['max_eq'] ?? 5);
    $maxSpells = (int)($zone['max_spells'] ?? 5);
    
    if ($cat >= 1 && $cat <= 4 && $cls >= $maxWep) api_error('Item out of stock range', 400);
    if ($cat >= 5 && $cat <= 8 && $cls >= $maxSpells) api_error('Item out of stock range', 400);
    if ($cat >= 9 && $cat <= 10 && $cls >= $maxEq) api_error('Item out of stock range', 400);
    
    $price = api_item_price_buy($cls);
    $gold = (int)$player['stats']['Gold'];
    
    if ($gold < $price) api_error('Not enough gold', 400);
    
    // Find empty inventory slot
    $inv = $player['inventory'];
    $emptySlot = -1;
    for ($i = 0; $i < 30; $i++) {
        if ($inv[$i] == 0) { $emptySlot = $i; break; }
    }
    if ($emptySlot == -1) api_error('Inventory full', 400);
    
    // Create item (encoding: type*1000000 + class*10000 + gem)
    $iclass = (int)floor($cls);
    switch ($cls) {
        case 33: $iclass = 36; break;
        case 34: $iclass = 37; break;
        case 35: $iclass = 38; break;
        case 36: $iclass = 41; break;
    }
    
    $itemId = ($cat * 1000000) + ($iclass * 10000);
    
    // Update DB
    $col = 'I' . $emptySlot;
    mysqli_query($dbx, "UPDATE Inventory SET $col=$itemId WHERE Id=$pid");
    $newGold = $gold - $price;
    mysqli_query($dbx, "UPDATE Stats SET Gold=$newGold WHERE Id=$pid");
    
    return [
        'action' => 'buy',
        'item' => api_item_info($itemId),
        'slot' => $emptySlot,
        'price' => $price,
        'goldRemaining' => $newGold,
    ];
}

function api_shop_sell($player, $slot) {
    global $dbx;
    $pid = (int)$player['Id'];
    
    if ($slot < 0 || $slot >= 30) api_error('Invalid slot', 400);
    
    $inv = $player['inventory'];
    $item = $inv[$slot];
    if ($item == 0) api_error('Empty slot', 400);
    
    // Check if equipped
    $slotNum = $slot + 1;
    $eq = $player['equipped'];
    if ($eq['rhand'] == $slotNum || $eq['lhand'] == $slotNum || 
        $eq['spellone'] == $slotNum || $eq['spelltwo'] == $slotNum ||
        $eq['accx'] == $slotNum) {
        api_error('Cannot sell equipped item', 400);
    }
    
    // Get sell price (check kingdom bonus)
    $clan = (int)$player['stats']['Clan'] ?? 0;
    $kdMisc = 0;
    if ($clan > 0) {
        $kQ = mysqli_query($dbx, "SELECT Kd_misc FROM Kingdoms WHERE Kd_clan=$clan");
        if ($kQ && mysqli_num_rows($kQ) > 0) {
            $kRow = mysqli_fetch_assoc($kQ);
            $kdMisc = (int)$kRow['Kd_misc'];
        }
    }
    $sellMult = $kdMisc == 5 ? 1.5 : 1.0;
    
    $tstatus = (int)$player['stats']['Tstatus'] ?? 0;
    $price = (int)round(api_item_price_sell($item, $tstatus) * $sellMult);
    
    // Update DB
    $col = 'I' . $slot;
    mysqli_query($dbx, "UPDATE Inventory SET $col=0 WHERE Id=$pid");
    $newGold = (int)$player['stats']['Gold'] + $price;
    mysqli_query($dbx, "UPDATE Stats SET Gold=$newGold WHERE Id=$pid");
    
    return [
        'action' => 'sell',
        'slot' => $slot,
        'soldItem' => api_item_info($item),
        'price' => $price,
        'goldTotal' => $newGold,
    ];
}

function api_item_equip($player, $slot) {
    global $dbx;
    $pid = (int)$player['Id'];
    
    if ($slot < 0 || $slot >= 30) api_error('Invalid slot', 400);
    
    $inv = $player['inventory'];
    $item = $inv[$slot];
    if ($item == 0) api_error('Empty slot', 400);
    
    $info = api_item_info($item);
    $slotNum = $slot + 1;
    $stats = $player['stats'];
    
    $str = (int)$stats['Str'];
    $dex = (int)$stats['Dex'];
    $vit = (int)$stats['Vit'];
    $ntl = (int)$stats['Ntl'];
    $wis = (int)$stats['Wis'];
    $lvl = (int)$stats['Lvl'];
    
    $type = $info['type'];
    $num = $info['class'];
    
    // Check level requirements
    $eqReqMet = true;
    if ($type < 11) {
        if ($lvl < max(20 * $num - 200, 0) && $num > 10) $eqReqMet = false;
    } elseif ($type < 21) {
        if ($lvl < 20 * ($num - 10) && $num > 9) $eqReqMet = false;
    } elseif ($type == 30 || $type == 28) {
        if ($lvl < 20 * $num && $num > 4) $eqReqMet = false;
    } elseif ($type < 41) {
        if ($lvl < 20 * ($num - 5) && $num > 4) $eqReqMet = false;
    }
    
    if (!$eqReqMet) api_error('Level too low to equip this item', 400);
    
    // Check stat requirements
    $stqx = ($type > 10 ? 1 : 0) + ($type > 20 ? 1 : 0);
    
    if ($type % 10 == 0 && $type > 0 && $type < 20) {
        // Shield - str req
        if ($str < (120 - $stqx * 50) * $num) api_error('Not enough Strength', 400);
    } elseif ($type % 10 < 5 && $type < 41) {
        // Weapon - str req
        if ($str < (200 - $stqx * 50) * $num) api_error('Not enough Strength', 400);
    } elseif ($type % 10 < 9 && $type < 41) {
        // Spell - ntl req
        if ($ntl < (200 - $stqx * 50) * $num) api_error('Not enough Intelligence', 400);
    } elseif ($type % 10 == 9) {
        // Armor - vit req
        if ($vit < (150 - $stqx * 50) * $num) api_error('Not enough Vitality', 400);
    }
    
    // Equip into appropriate slot
    $col = '';
    $slotName = '';
    $eq = $player['equipped'];
    
    if ($type % 10 < 5 && $type < 41) {
        // Weapon slots: rhand, lhand
        if ($eq['rhand'] == 0 && $eq['lhand'] != $slotNum) {
            $col = 'Rhand'; $slotName = 'rhand';
        } elseif ($eq['rhand'] == $slotNum) {
            $col = 'Rhand'; $slotName = 'rhand'; // unequip
            $slotNum = 0;
        } elseif ($eq['lhand'] == 0) {
            $col = 'Lhand'; $slotName = 'lhand';
        } elseif ($eq['lhand'] == $slotNum) {
            $col = 'Lhand'; $slotName = 'lhand';
            $slotNum = 0;
        } else {
            api_error('Both hands are full. Unequip first.', 400);
        }
    } elseif ($type % 10 < 9 && $type < 41) {
        // Spell slots
        if ($eq['spellone'] == 0 && $eq['spelltwo'] != $slotNum) {
            $col = 'Spellone'; $slotName = 'spellone';
        } elseif ($eq['spellone'] == $slotNum) {
            $col = 'Spellone'; $slotName = 'spellone';
            $slotNum = 0;
        } elseif ($eq['spelltwo'] == 0) {
            $col = 'Spelltwo'; $slotName = 'spelltwo';
        } elseif ($eq['spelltwo'] == $slotNum) {
            $col = 'Spelltwo'; $slotName = 'spelltwo';
            $slotNum = 0;
        } else {
            api_error('Both spell slots are full. Unequip first.', 400);
        }
    } elseif ($type % 10 == 9) {
        // Armor
        if ($eq['armor'] == 0) {
            // We don't have a clear armor column... check inventory
            // Actually looking at the code, 'armor' isn't a direct column
            // The original uses the inventory position
            api_error('Armor equipping not yet supported via API. Use legacy client.', 400);
        } else {
            api_error('Unequip current armor first', 400);
        }
    } elseif ($type == 41) {
        // Accessory
        if ($eq['accx'] == 0) {
            $col = 'Accx'; $slotName = 'accx';
        } elseif ($eq['accx'] == $slotNum) {
            $col = 'Accx'; $slotName = 'accx';
            $slotNum = 0;
        } else {
            api_error('Accessory slot full. Unequip first.', 400);
        }
    } else {
        api_error('Cannot equip this item type', 400);
    }
    
    mysqli_query($dbx, "UPDATE Inventory SET $col=$slotNum WHERE Id=$pid");
    
    return [
        'action' => $slotNum == 0 ? 'unequip' : 'equip',
        'slot' => $slot,
        'item' => $info,
        'equipSlot' => $slotName,
        'equipSlotValue' => $slotNum,
    ];
}

function api_item_unequip($player, $slotName) {
    global $dbx;
    $pid = (int)$player['Id'];
    
    $validSlots = ['rhand', 'lhand', 'spellone', 'spelltwo', 'accx'];
    $colMap = [
        'rhand' => 'Rhand', 'lhand' => 'Lhand',
        'spellone' => 'Spellone', 'spelltwo' => 'Spelltwo',
        'accx' => 'Accx',
    ];
    
    if (!in_array($slotName, $validSlots)) {
        api_error('Invalid equip slot. Valid: ' . implode(', ', $validSlots), 400);
    }
    
    $col = $colMap[$slotName];
    mysqli_query($dbx, "UPDATE Inventory SET $col=0 WHERE Id=$pid");
    
    return [
        'action' => 'unequip',
        'slot' => $slotName,
    ];
}

function api_item_combine($player, $itemSlot, $gemSlot) {
    global $dbx;
    $pid = (int)$player['Id'];
    
    if ($itemSlot < 0 || $itemSlot >= 30 || $gemSlot < 0 || $gemSlot >= 30) {
        api_error('Invalid slot', 400);
    }
    
    $inv = $player['inventory'];
    $item = $inv[$itemSlot];
    $gem = $inv[$gemSlot];
    
    if ($item < 9999) api_error('Not a valid item', 400);
    if ($gem >= 100 || $gem <= 0) api_error('Not a valid gem (must be 1-99)', 400);
    
    $type = api_ityper($item);
    $num = api_inumer($item);
    
    // Check item can accept gems
    if ($type > 0 && $type < 11 && $num < 15) {
        if (($item % 100) != 0) api_error('Item already has a gem in this slot', 400);
    } elseif ($type > 10 || $num > 14) {
        if (($item % 10000) == 0) {
            // Can add gem
        } elseif (($item % 10000) < 100) {
            // Can add second gem (×100)
        } else {
            api_error('Item already has gems', 400);
        }
    }
    
    // Combine
    if ($type > 10 || $num > 14) {
        if (($item % 10000) == 0) {
            $newItem = $item + $gem;
        } elseif (($item % 10000) < 100) {
            $newItem = $item + $gem * 100;
        } else {
            api_error('No more gem slots', 400);
        }
    } else {
        $newItem = $item + $gem;
    }
    
    $itemCol = 'I' . $itemSlot;
    $gemCol = 'I' . $gemSlot;
    
    mysqli_query($dbx, "UPDATE Inventory SET $itemCol=$newItem, $gemCol=0 WHERE Id=$pid");
    
    return [
        'action' => 'combine',
        'itemSlot' => $itemSlot,
        'gemSlot' => $gemSlot,
        'resultItem' => api_item_info($newItem),
    ];
}
