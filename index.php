<?php
// index.php
session_start();
require_once 'functions.php';

// --- LOGIC: DETECT "RETURN USER" ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && 
    isset($_SESSION['game_state']) && 
    $_SESSION['game_state'] != 'start' && 
    $_SESSION['game_state'] != 'rules' && 
    $_SESSION['game_state'] != 'welcome_back' && 
    $_SESSION['game_state'] != 'end_deal' && 
    $_SESSION['game_state'] != 'end_final'
   ) {
    $_SESSION['saved_state'] = $_SESSION['game_state'];
    $_SESSION['game_state'] = 'welcome_back';
}

// --- MAIN CONTROLLER ---
$action = $_POST['action'] ?? null;
$_SESSION['just_opened'] = null;

if ($action != 'open_case') { 
    $_SESSION['feedback_message'] = null;
    $_SESSION['feedback_type'] = null;
}

switch ($action) {
    case 'start_game':
        initialize_game();
        break;

    case 'resume_game':
        if (isset($_SESSION['saved_state'])) {
            $_SESSION['game_state'] = $_SESSION['saved_state'];
        } else {
            $_SESSION['game_state'] = 'start';
        }
        break;

    case 'new_game_confirm':
        initialize_game();
        break;

    case 'show_rules':
        $_SESSION['saved_state'] = $_SESSION['game_state'];
        $_SESSION['game_state'] = 'rules';
        break;

    case 'back_from_rules':
        $_SESSION['game_state'] = 'start';
        break;

    case 'pick_player_case':
        $_SESSION['feedback_message'] = null;
        $_SESSION['feedback_type'] = null;
        if (isset($_POST['case_id'])) {
            $picked_id = $_POST['case_id'];
            $_SESSION['player_case'] = $picked_id;
            $_SESSION['cases'][$picked_id]['status'] = 'player';
            $_SESSION['game_state'] = 'playing';
        }
        break;

    case 'open_case':
        if (isset($_POST['case_id'])) {
            $opened_id = $_POST['case_id'];
            
            $_SESSION['cases'][$opened_id]['status'] = 'open';
            $_SESSION['just_opened'] = $opened_id;
            
            $value_opened = $_SESSION['cases'][$opened_id]['value']; 
            $_SESSION['revealed_values'][] = $value_opened;
            $_SESSION['cases_opened_this_round']++;
            
            // Feedback Logic
            if ($value_opened >= 750000) {
                $_SESSION['feedback_message'] = "Catastrophic! A top prize is gone!";
                $_SESSION['feedback_type'] = 'bad';
            } elseif ($value_opened >= 200000) {
                $_SESSION['feedback_message'] = "Oh no! That's one of the Big Six!";
                $_SESSION['feedback_type'] = 'bad';
            } elseif ($value_opened >= 50000) {
                $_SESSION['feedback_message'] = "Ouch. That's a serious chunk of change.";
                $_SESSION['feedback_type'] = 'bad';
            } elseif ($value_opened <= 10) {
                $_SESSION['feedback_message'] = "YES! Fantastic pick! Tiny value gone.";
                $_SESSION['feedback_type'] = 'good';
            } elseif ($value_opened <= 1000) {
                $_SESSION['feedback_message'] = "Nice! Keeping the board safe.";
                $_SESSION['feedback_type'] = 'good';
            } else {
                $_SESSION['feedback_message'] = "Okay, a middle value. We can live with that.";
                $_SESSION['feedback_type'] = 'neutral'; 
            }
            
            check_for_volatile_event(); 

            // --- FIX: CHECK FOR GAME OVER BEFORE BANKER ---
            $remaining_closed = 0;
            foreach ($_SESSION['cases'] as $c) {
                if ($c['status'] == 'closed') $remaining_closed++;
            }

            if ($remaining_closed == 0) {
                // NO CASES LEFT ON BOARD -> GAME OVER IMMEDIATELY
                $_SESSION['game_state'] = 'end_final';
                $_SESSION['final_winnings'] = $_SESSION['cases'][$_SESSION['player_case']]['value'];
            } else {
                // Game continues -> Check if Round Over
                $cases_to_open = get_cases_to_open_for_round();
                if ($_SESSION['cases_opened_this_round'] >= $cases_to_open) {
                    $_SESSION['banker_waiting'] = true;
                    $_SESSION['current_offer'] = get_banker_offer();
                }
            }
        }
        break;
        
    case 'answer_phone':
        $_SESSION['game_state'] = 'offer';
        $_SESSION['banker_waiting'] = false;
        $_SESSION['cases_opened_this_round'] = 0;
        break;

    case 'deal':
        $_SESSION['game_state'] = 'end_deal';
        $_SESSION['final_winnings'] = $_SESSION['current_offer'];
        break;

    case 'no_deal':
        $_SESSION['feedback_message'] = null;
        $_SESSION['feedback_type'] = null;
        $remaining_cases = 0;
        foreach ($_SESSION['cases'] as $case) {
            if ($case['status'] == 'closed') $remaining_cases++;
        }

        if ($remaining_cases == 0) {
            $_SESSION['game_state'] = 'end_final';
            $_SESSION['final_winnings'] = $_SESSION['cases'][$_SESSION['player_case']]['value'];
        } else {
            $_SESSION['game_state'] = 'playing';
        }
        break;

    case 'play_again':
        session_destroy();
        header("Location: index.php");
        exit;
}

if (!isset($_SESSION['game_state'])) {
    $_SESSION['game_state'] = 'start';
}
$game_state = $_SESSION['game_state'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deal or No Deal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <?php if ($game_state == 'start'): ?>
        <div class="screen screen-start">
            <div style="text-align: center;">
                <form method="POST" style="margin-bottom: 20px;">
                    <input type="hidden" name="action" value="start_game">
                    <button type="submit" class="btn-action">Start Game</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="action" value="show_rules">
                    <button type="submit" class="btn-action" style="background-color: #34495e; font-size: 1.2rem;">How to Play</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($game_state == 'welcome_back'): ?>
        <div class="screen screen-welcome-back">
            <div class="resume-box">
                <h2>Game in Progress</h2>
                <p>Would you like to continue?</p>
                <div class="resume-buttons">
                    <form method="POST">
                        <input type="hidden" name="action" value="resume_game">
                        <button type="submit" class="btn-action btn-resume">Resume Game</button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="action" value="new_game_confirm">
                        <button type="submit" class="btn-action btn-new">New Game</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($game_state == 'rules'): ?>
        <div class="screen screen-start"> 
            <div class="rules-box">
                <h2>HOW TO PLAY</h2>
                <ul>
                    <li><strong>Step 1:</strong> Choose your lucky briefcase.</li>
                    <li><strong>Step 2:</strong> Open briefcases to eliminate values.</li>
                    <li><strong>Step 3:</strong> Negotiate with the Banker.</li>
                    <li><strong>Volatile Events:</strong> Values may randomly Crash (Halve), Boom (Double), Inflate (+15%), or Tax (-10%)!</li>
                </ul>
                <form method="POST" style="text-align: center;">
                    <input type="hidden" name="action" value="back_from_rules">
                    <button type="submit" class="btn-action">Back to Menu</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="game-wrapper">

        <?php if ($game_state != 'start' && $game_state != 'rules' && $game_state != 'welcome_back'): 
            
            // --- BOARD GENERATION SETUP ---
            $original_money_values = get_money_values();
            sort($original_money_values, SORT_NUMERIC);
        ?>

        <div class="money-board money-board-left">
            <?php
            for ($i = 0; $i < 13; $i++) {
                $original = $original_money_values[$i];
                
                $found_case = null;
                foreach ($_SESSION['cases'] as $c) {
                    if (abs($c['original_value'] - $original) < 0.001) {
                        $found_case = $c;
                        break;
                    }
                }

                $class = '';
                $display_html = "<span>$" . number_format($original) . "</span>"; 

                if ($found_case) {
                    if ($found_case['status'] == 'open') {
                        $class = 'revealed';
                    }
                    
                    if (abs($found_case['value'] - $found_case['original_value']) > 0.001) {
                        $new_display = number_format($found_case['value']);
                        if ($found_case['value'] < 1) { $new_display = number_format($found_case['value'], 2); }
                        
                        $display_html = "<span class='val-struck'>$" . number_format($original) . "</span> <span class='val-new'>$" . $new_display . "</span>";
                    } else {
                        $display_html = "<span>$" . number_format($original) . "</span>";
                    }
                }

                echo "<div class='money-bar $class'>";
                echo $display_html;
                echo "</div>";
            }
            ?>
        </div>

        <div class="game-container">
            
            <?php if (!empty($_SESSION['flash_message'])): ?>
                <div class="flash-message">
                    <strong><?php echo $_SESSION['flash_message']; ?></strong>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['feedback_message'])): ?>
                <div class="feedback-message <?php echo $_SESSION['feedback_type']; ?>">
                    <strong><?php echo $_SESSION['feedback_message']; ?></strong>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['offer_history'])): ?>
                <div class="history-container">
                    <h4>Offer History</h4>
                    <div class="history-list">
                        <?php 
                        $offers = $_SESSION['offer_history'];
                        $offers = array_reverse($offers); 
                        foreach ($offers as $offer): ?>
                            <span class="history-tag">$<?php echo number_format($offer); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($game_state == 'playing' || $game_state == 'offer'): ?>
                <div class="player-case-box">
                    <h3 class="game-title">YOUR CASE</h3>
                    <div class="case-button-visual is-player">
                        <span class="case-number"><?php echo $_SESSION['player_case']; ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($game_state == 'pick_case'): ?>
                <div class="screen screen-pick-case">
                    <h2 class="game-title">CHOOSE YOUR CASE</h2>
                    <div class="briefcase-grid">
                        <?php foreach ($_SESSION['cases'] as $case): ?>
                            <form method="POST" class="case-form">
                                <input type="hidden" name="action" value="pick_player_case">
                                <input type="hidden" name="case_id" value="<?php echo $case['id']; ?>">
                                <button type="submit" class="case-button-visual">
                                    <span class="case-number"><?php echo $case['id']; ?></span>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($game_state == 'playing'): ?>
                <div class="screen screen-playing">
                    
                    <?php if (isset($_SESSION['banker_waiting']) && $_SESSION['banker_waiting']): ?>
                        <div style="text-align: center; margin-bottom: 20px;">
                            <form method="POST">
                                <input type="hidden" name="action" value="answer_phone">
                                <button type="submit" class="btn-action" style="background-color: #f39c12; font-size: 1.5rem; box-shadow: 0 0 20px orange; animation: pulse 1s infinite;">
                                    📞 ANSWER BANKER CALL
                                </button>
                            </form>
                        </div>
                        <style>
                            @keyframes pulse {
                                0% { transform: scale(1); }
                                50% { transform: scale(1.05); }
                                100% { transform: scale(1); }
                            }
                        </style>
                    <?php else: ?>
                        <?php
                            $to_open = get_cases_to_open_for_round();
                            $opened_this_round = $_SESSION['cases_opened_this_round'];
                            $remaining_picks = $to_open - $opened_this_round;
                        ?>
                        <h2 class="game-title">
                            Open <?php echo $remaining_picks; ?> 
                            case(s).
                        </h2>
                    <?php endif; ?>

                    <div class="briefcase-grid">
                        <?php foreach ($_SESSION['cases'] as $case): ?>
                            <?php
                                if ($case['status'] == 'player') continue;
                                $disabled = false;
                                
                                if (isset($_SESSION['banker_waiting']) && $_SESSION['banker_waiting']) {
                                    $disabled = true;
                                }

                                $class = 'case-button-visual';
                                $content_html = '';

                                if ($case['status'] == 'open') {
                                    $disabled = true;
                                    $class .= ' is-open';
                                    $val_display = ($case['value'] < 1) ? number_format($case['value'], 2) : number_format($case['value']);
                                    $content_html = '<span class="case-value">$' . $val_display . '</span>';
                                    if ($case['id'] == ($_SESSION['just_opened'] ?? null)) {
                                        $class .= ' animate-open';
                                    }
                                } else {
                                    $content_html = '<span class="case-number">' . $case['id'] . '</span>';
                                }
                            ?>
                            <form method="POST" class="case-form">
                                <input type="hidden" name="action" value="open_case">
                                <input type="hidden" name="case_id" value="<?php echo $case['id']; ?>">
                                <button type="submit" class="<?php echo $class; ?>" <?php if ($disabled) echo 'disabled'; ?>>
                                    <?php echo $content_html; ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($game_state == 'offer'): ?>
                <div class="screen screen-offer">
                    <h2 class="game-title">The Banker is Calling...</h2>
                    <div class="offer-box">
                        <h3>Banker's Strategic Offer:</h3>
                        <div class="offer-amount">
                            $<?php echo number_format($_SESSION['current_offer']); ?>
                        </div>
                        <div class="offer-buttons">
                            <form method="POST">
                                <input type="hidden" name="action" value="deal">
                                <button type="submit" class="btn-action btn-deal">DEAL</button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="action" value="no_deal">
                                <button type="submit" class="btn-action btn-no-deal">NO DEAL</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($game_state == 'end_deal' || $game_state == 'end_final'): ?>
                <div class="screen screen-end">
                    <h2 class="game-title">Game Over</h2>
                    <?php if ($game_state == 'end_deal'): ?>
                        <h3>You accepted the deal!</h3>
                        <p class="final-winnings">You won: $<?php echo number_format($_SESSION['final_winnings']); ?></p>
                    <?php else: ?>
                        <h3>You played to the end!</h3>
                        <p class="final-winnings">Your case had: $<?php 
                            $final_val = $_SESSION['final_winnings'];
                            $final_display = ($final_val < 1) ? number_format($final_val, 2) : number_format($final_val);
                            echo $final_display;
                        ?></p>
                    <?php endif; ?>
                    <p>The value in your case (<?php echo $_SESSION['player_case']; ?>) was: 
                        <strong>$<?php 
                            $player_val = $_SESSION['cases'][$_SESSION['player_case']]['value'];
                            $player_display = ($player_val < 1) ? number_format($player_val, 2) : number_format($player_val);
                            echo $player_display; 
                        ?></strong>
                    </p>
                    <form method="POST">
                        <input type="hidden" name="action" value="play_again">
                        <button type="submit" class="btn-action">Play Again</button>
                    </form>
                </div>
            <?php endif; ?>

        </div> 
        
        <div class="money-board money-board-right">
            <?php
            for ($i = 13; $i < 26; $i++) {
                $original = $original_money_values[$i];
                
                $found_case = null;
                foreach ($_SESSION['cases'] as $c) {
                    if (abs($c['original_value'] - $original) < 0.001) {
                        $found_case = $c;
                        break;
                    }
                }

                $class = '';
                $display_html = "<span>$" . number_format($original) . "</span>";

                if ($found_case) {
                    if ($found_case['status'] == 'open') {
                        $class = 'revealed';
                    }

                    if (abs($found_case['value'] - $found_case['original_value']) > 0.001) {
                        $new_display = number_format($found_case['value']);
                        if ($found_case['value'] < 1) { $new_display = number_format($found_case['value'], 2); }
                        
                        $display_html = "<span class='val-struck'>$" . number_format($original) . "</span> <span class='val-new'>$" . $new_display . "</span>";
                    } else {
                        $display_html = "<span>$" . number_format($original) . "</span>";
                    }
                }

                echo "<div class='money-bar $class'>";
                echo $display_html;
                echo "</div>";
            }
            ?>
        </div>
        
        <?php endif; ?>
    </div> </body>
</html>