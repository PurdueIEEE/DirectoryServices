<?php
    require_once 'secrets.php'; // THIS FILE IS NOT CHECKED INTO GIT.
    require_once 'lists.php';

    // If this script is not called with a `token`, then initiate the changes.
    // Otherwise, we just need to confirm the changes, and verify the token.
    if (!isset($_GET['t']) {

        // Sanitize the email and get the user's current subs and future subs.
        $email = preg_replace('/[^a-z0-9@_+.-]/i', '_', $_POST['email']);
        $all_lists = Lists::all(true);
        $current_subs = Lists::find($email);
        $future_subs = array_filter($_POST['list'], function($v) {
            return in_array($v, $all_lists);
        });

        // Isolate the additions and removals based on subscription request.
        $to_add = array_filter($future_subs, function($v) {
            return !in_array($v, $current_subs);
        });
        $to_rem = array_filter($current_subs, function($v) {
            return !in_array($v, $future_subs);
        });

        // Encode a JWToken and return it. The token should be confirmed before expiry, by the subject.
        $token = JWT::encode([
            'exp'  => time() + (60*60*24*3), // 3 day expiry
            'sub' => $email,
            'add' => $to_add,
            'rem' => $to_rem
        ], TOKEN_SECRET, 'HS512');

        // Return a summary of the changes to be made to display to the user.
        $msg = "You'll be added to the following directories:";
        foreach ($to_add as $value) {
            $msg .= "&bull; $value<br>";
        }
        $msg .= "<br>You'll be removed from the following directories:";
        foreach ($to_remove as $value) {
            $msg .= "&bull; $value<br>";
        }
        $msg_return = "$msg<br><br>An email has been sent to you to confirm these changes before they are made.<br>";

        // Send the mail and return the summary.
        mail($email, "Please confirm changes to your directories.",
            "$msg<br>Please confirm your mailing list changes by going to this link: {$_SERVER['SCRIPT_FILENAME']}.");
        exit($msg_return);
    } else {
        try {
            $token = JWT::decode($_GET['t'], TOKEN_SECRET, ['HS512']);
        } catch(ExpiredException $e) {
            http_response_code(400);
            exit("token has expired");
        } catch(Exception $e) {
            http_response_code(400);
            exit("error decoding token");
        }

        // Unpack our token if we were able to decode it correctly.
        $email = $token['sub'];
        $to_add = $token['add'];
        $to_rem = $token['rem'];

        // Finally commit the changes from the token to the user.
        $errors = [];
        foreach ($to_add as $value) {
            if (!Lists::add($value, $email))
                $errors[] = "&bull; Failed to add directory $value.<br>";
        }
        foreach ($to_remove as $value) {
            if (!Lists::remove($value, $email))
                $errors[] = "&bull; Failed to remove directory $value.<br>";
        }

        // Pretty-print the output and we're done here.
        $msg = "Success! Your changes have been made.<br>";
        if (count($errors) > 0)
            $msg .= "The following errors occurred.<br>";
        foreach ($errors as $value) {
            $msg .= $value;
        }
        exit($msg);
    }
?>
