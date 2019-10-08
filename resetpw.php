<?php
// Password Reset Module
// Metatheria, LLC 2019 Alex Clark
// GNU GPLv3

//uncomment for debugging errors.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// We want pika-danio.php classes and functions to be available and want to use Pika's database connection, but we also don't want the user to be forced to authenticate since this is a pw reset module.
define('PL_DISABLE_SECURITY', true);
require_once('pika-danio.php');
pika_init();

//load mailgun
require_once('services/vendor/autoload.php');
$email = new \SendGrid\Mail\Mail(); 
$email->setFrom("noreply@resets.metatheria.solutions", "Pika");
$email->setSubject("Lost Password");

//token generation function
$permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
function generate_string($input, $strength = 50) {
    $input_length = strlen($input);
    $random_string = '';
    for($i = 0; $i < $strength; $i++) {
        $random_character = $input[mt_rand(0, $input_length - 1)];
        $random_string .= $random_character;
    }

    return $random_string;
}

echo "<!DOCTYPE html>";
echo "<html lang=\"en\">";
echo "<head>";
echo "  <title> OCM | Reset Lost/Expired Password</title>";
echo "  <meta charset=\"utf-8\"";
echo "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">";
echo "  <link rel=\"stylesheet\" href=\"https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css\">";
echo "  <script src=\"https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js\"></script>";
echo "  <script src=\"https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js\"></script>";
echo "  <script src=\"https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js\"></script>";
echo "</head>";
echo "<body style=\"background: #397bd4; color: #fff; margin-top: 15%;\">";
echo "  <div class=\"container span6\">";
echo "<h2>Pika/OCM Password Reset</h2>";


if (isset($_POST['username'])){
  $safe_username=DB::escapeString($_POST['username']);
  $sql = "select user_id, username, email from users where username='" . $safe_username . "'";
  $result = DB::query($sql) or trigger_error("SQL: " . $sql . " ERROR: " . DB::error());
  
  if (DBResult::numRows($result) == 1) {
    //echo "<pre>";
    while ($row = DBResult::fetchRow($result))
    { 
      $emailFromDB = $row['email'];
    }
      //clean any old tokens for user from the db
      $clean_sql = "DELETE FROM pw_reset_tokens where username='" . $safe_username . "'";
      DB::query($clean_sql) or trigger_error("SQL: " . $clean_sql . " ERROR: " . DB::error());
      //create a new token for the user
      $resetToken = generate_string($permitted_chars, 50);
      $token_sql = "INSERT INTO pw_reset_tokens (username, token, token_expire) VALUES ('" . $safe_username . "', '" . $resetToken . "', '" . strtotime( '+3600 seconds' ) . "')"; // 3600 corresponds to the number of seconds in one hour. Change this for a different interval of time that the reset tokens should stay good for. 
      DB::query($token_sql) or trigger_error("SQL: " . $token_sql . " ERROR: " . DB::error());
      //email the user their reset link
        $email->addTo($emailFromDB, "");
        $email->addContent("text/html", 'Please visit https://' . $_SERVER['SERVER_NAME'] . pl_settings_get('base_url') . "/resetpw.php?token="  . $resetToken . ' to reset your password');
        $sendgrid = new \SendGrid('API_KEY_GOES_HERE');
	  try {
    	    $response = $sendgrid->send($email);
            //print $response->statusCode() . "\n";
            //print_r($response->headers());
            //print $response->body() . "\n";
          } catch (Exception $e) {
            echo 'Caught exception: '. $e->getMessage() ."\n";
          }
        echo "Please check your email. The password reset link was sent to $emailFromDB";
  } 
  else {
  echo "Invalid username. Please try again.";
  echo "<form method=\"POST\" name=\"resetpw\"> Please enter your username.<br>";
  echo "<input type=\"text\" name=\"username\" id=\"username\">";
  echo "<button type=\"submit\">Submit</button>";
  echo "</form>"; 
  }
}
else {
  if (!isset($_POST['username']) && !isset($_POST['token']) && isset($_GET['token'])) {
    $safe_token = DB::escapeString($_GET['token']);
    $token_search_sql = "select username, token, token_expire from pw_reset_tokens where token='" . $safe_token  . "' and token_expire > " . strtotime("now");
    $token_search_result = DB::query($token_search_sql) or trigger_error("SQL: " . $token_search_sql . " ERROR: " . DB::error());
    if (DBResult::numRows($token_search_result) == 1) {
      while ($row = DBResult::fetchRow($token_search_result)) {
        $forgetful_user = $row['username'];
      }
      $temppass = generate_string($permitted_chars, 14);
      $reset_sql ="update users set password='";
      $reset_sql .= md5($temppass);
      $reset_sql .= "', password_expire='";
      $reset_sql .= strtotime('+660 seconds'); // 11 minutes
      $reset_sql .= "' where username='";
      $reset_sql .= $forgetful_user;
      $reset_sql .= "'";
      DB::query($reset_sql) or trigger_error("SQL: " . $reset_sql . " ERROR: " . DB::error());
      echo "Your password has been temporarily reset to <strong>$temppass</strong>. Please click <a href=\"password.php\" target=\"_blank\" style=\"color: #d49239\">here</a> to log in with this temporary password and set a password of your choice. Please note that when the password change screen asks for your \"Current Password\" it is asking for <strong>$temppass</strong>. Your temporary password of <strong>$temppass</strong> will expire in 10 minutes, so please change your password now.";
      }
    else {
        echo "The password reset link is either invalid or expired. ";
      }
  }
  else {
    echo "<form method=\"POST\" name=\"resetpw\"> Please enter your username.<br>";
    echo "<input type=\"text\" name=\"username\" id=\"username\">";
    echo "<button type=\"submit\">Submit</button>";
    echo "</form>";
  }
}

echo "</div>";
