<?php
    require_once 'secrets.php'; // THIS FILE IS NOT CHECKED INTO GIT.
    require_once 'lists.php';
    require_once 'jwt.php';

    // If this script is not called with a `token`, then initiate the changes.
    // Otherwise, we just need to confirm the changes, and verify the token.
    if (!isset($_GET['t'])) {

        // Sanitize the email and get the user's current subs and future subs.
        $email = preg_replace('/[^a-z0-9@_+.-]/i', '_', $_POST['email']);
        $all_lists = Lists::all();
        $current_subs = Lists::find($email);
        $future_subs = array_filter($_POST['list'], function($v) use (&$all_lists) {
            return in_array($v, $all_lists);
        });

        // Isolate the additions and removals based on subscription request.
        // Return early if no changes were actually made.
        $to_add = array_filter($future_subs, function($v) use (&$current_subs) {
            return !in_array($v, $current_subs);
        });
        $to_rem = array_filter($current_subs, function($v) use (&$future_subs) {
            return !in_array($v, $future_subs);
        });
        if (count($to_add) === 0 && count($to_rem) === 0) {
            exit("No changes were selected.");
        }

        // Encode a JWToken and return it. The token should be confirmed before expiry, by the subject.
        $token = JWT::encode([
            'exp'  => time() + (60*60*24*3), // 3 day expiry
            'sub' => $email,
            'add' => $to_add,
            'rem' => $to_rem
        ], TOKEN_SECRET, 'HS512');

        // Return a summary of the changes to be made to display to the user.
        $msg = "You'll be added to the following directories:\n";
        foreach ($to_add as $value) {
            $msg .= "\u{25CF} $value\n";
        }
        $msg .= "\nYou'll be removed from the following directories:\n";
        foreach ($to_rem as $value) {
            $msg .= "\u{25CF} $value\n";
        }
        $msg_return = "$msg\nAn email has been sent to you to confirm these changes before they are made.\n";

        // Send the mail and return the summary.
        $headers .= 'From: ' . DIRECTORY_ADMIN . "\r\n";
        $server = $_SERVER['SERVER_NAME'] . '' . $_SERVER['REQUEST_URI'];
        mail($email, "Please confirm changes to your directories",
             "$msg\nPlease confirm your mailing list changes by going to this link: https://$server?t=$token\n",
             $headers);
        exit(nl2br($msg_return));
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
        $email = $token->sub;
        $to_add = $token->add;
        $to_rem = $token->rem;

        // Finally commit the changes from the token to the user.
        $changes = [];
        foreach ($to_add as $value) {
            if (!Lists::add($value, $email))
                $changes[] = "\u{25CF} Failed to add directory $value.\n";
            else $changes[] = "\u{25CF} Added directory $value.\n";
        }
        foreach ($to_rem as $value) {
            if (!Lists::remove($value, $email))
                $changes[] = "\u{25CF} Failed to remove directory $value.\n";
            else $changes[] = "\u{25CF} Removed directory $value.\n";
        }

        // Pretty-print the output and we're done here.
        $msg = "Success! The following changes have been made:\n";
        foreach ($changes as $value)
            $msg .= $value;
        exit(nl2br($msg));
    }
?>
