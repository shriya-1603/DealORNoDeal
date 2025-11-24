<?php
// functions.php file

function get_money_values() {
    return [
        0.01, 1, 5, 10, 25, 50, 75, 100, 200, 300, 400, 500, 750,
        1000, 5000, 10000, 25000, 50000, 75000, 100000, 200000,
        300000, 400000, 500000, 750000, 1000000
    ];
}

function initialize_game() {
    $values = get_money_values();
    shuffle($values);

    $_SESSION['cases'] = [];
    for ($i = 0; $i < 26; $i++) {
        $_SESSION['cases'][$i + 1] = [
            'id' => $i + 1,
            'value' => $values[$i],
            'original_value' => $values[$i], // NEW: Track what it started as
            'status' => 'closed'
        ];
    }

    $_SESSION['game_state'] = 'pick_case';
    $_SESSION['player_case'] = null;
    $_SESSION['cases_opened_this_round'] = 0;
    $_SESSION['revealed_values'] = []; // Stores values at the moment they were opened
    $_SESSION['offer_history'] = [];
    $_SESSION['flash_message'] = null;
    $_SESSION['volatility_factor'] = 1.0; 
}
/**
 * UPDATED FEATURE #4: Dynamic Round Structure (Game Dependent)
 * Instead of fixed 6-5-4, this calculates based on "Risk".
 * If many high values (> $100k) are left, you must open MORE cases.
 */
function get_cases_to_open_for_round() {
    $remaining_cases = 0;
    $high_values_left = 0;

    foreach ($_SESSION['cases'] as $case) {
        if ($case['status'] == 'closed') {
            $remaining_cases++;
            if ($case['value'] >= 100000) {
                $high_values_left++;
            }
        }
    }

    // Base logic
    $round_limit = 1;

    if ($remaining_cases > 20) {
        $round_limit = 6;
    } elseif ($remaining_cases > 15) {
        // Dynamic: If risky (4+ high values), open 5. If safe, open 4.
        $round_limit = ($high_values_left >= 4) ? 5 : 4;
    } elseif ($remaining_cases > 10) {
        // Dynamic: If risky (3+ high values), open 4. If safe, open 3.
        $round_limit = ($high_values_left >= 3) ? 4 : 3;
    } elseif ($remaining_cases > 5) {
        // Dynamic: Randomize slightly to keep them on toes
        $round_limit = rand(2, 3);
    }

    return $round_limit;
}

function get_banker_offer() {
    $remaining_sum = 0;
    $remaining_count = 0;
    $high_values_left = 0;

    foreach ($_SESSION['cases'] as $case) {
        // FIX: Include both 'closed' cases AND the 'player' case
        if ($case['status'] == 'closed' || $case['status'] == 'player') {
            $remaining_sum += $case['value'];
            $remaining_count++;
            if ($case['value'] >= 100000) {
                $high_values_left++;
            }
        }
    }

    if ($remaining_count == 0) return 0;

    $ev = $remaining_sum / $remaining_count;
    $offer_percentage = 0.7; 
    
    if ($high_values_left <= 2 && $remaining_count > 5) {
        $offer_percentage = 0.90; 
    }
    elseif ($high_values_left > 5) {
        $offer_percentage = 0.60; 
    }

    // Adjust percentage slightly based on how far into the game we are
    $offer_percentage += (26 - $remaining_count) * 0.01; 

    $offer = $ev * $offer_percentage;
    
    $_SESSION['offer_history'][] = round($offer);
    
    return round($offer);
}

/**
 * UPDATED FEATURE #1: Random Volatile Events
 * Now includes 4 types of events.
 */
function check_for_volatile_event() {
    $_SESSION['flash_message'] = null;

    // 15% chance to trigger an event (slightly higher frequency)
    if (rand(1, 100) <= 15) {
        
        // Pick a random event type
        $event_type = rand(1, 4);
        $factor = 1.0;

        switch ($event_type) {
            case 1: // MARKET CRASH (Severe)
                $factor = 0.5;
                $_SESSION['flash_message'] = "📉 MARKET CRASH! High values have been HALVED!";
                break;
            case 2: // MARKET BOOM (Severe)
                $factor = 2.0;
                $_SESSION['flash_message'] = "📈 MARKET BOOM! Low values have DOUBLED!";
                break;
            case 3: // RECESSION (Minor)
                $factor = 0.9; // -10%
                $_SESSION['flash_message'] = "📉 RECESSION! All values dropped by 10%!";
                break;
            case 4: // INFLATION (Minor)
                $factor = 1.15; // +15%
                $_SESSION['flash_message'] = "📈 INFLATION! All values increased by 15%!";
                break;
        }

        // Apply the math
        foreach ($_SESSION['cases'] as $id => $case) {
            if ($case['status'] == 'closed' || $case['status'] == 'player') {
                // For Crash/Boom, we stick to specific ranges logic
                if ($event_type == 1 && $case['value'] >= 50000) {
                     $_SESSION['cases'][$id]['value'] *= $factor;
                } elseif ($event_type == 2 && $case['value'] <= 1000) {
                     $_SESSION['cases'][$id]['value'] *= $factor;
                } elseif ($event_type == 3 || $event_type == 4) {
                    // For Recession/Inflation, apply to EVERYTHING
                    $_SESSION['cases'][$id]['value'] *= $factor;
                }
            }
        }
        
        // Store factor so index.php knows how to draw the strikethrough
        $_SESSION['volatility_factor'] = $factor;
    }
}
?>
