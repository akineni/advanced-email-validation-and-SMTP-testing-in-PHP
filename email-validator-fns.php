<?php

    $debug = false;

    require 'includes/inc.role-based-email-keywords.php';

    // $disposable_domain_str TO BE CACHED
    // Get the online file incase it is being maintained and updated
    $disposable_domain_str = file_get_contents('https://raw.githubusercontent.com/ivolo/disposable-email-domains/master/index.json');

    // if unable to get the updated list, get the local file.
    if (!$disposable_domain_str) $disposable_domain_str = file_get_contents('includes/inc.disposable-domains-from-raw.githubusercontent.com.json');

    function hasMXRecords(string $domain): bool {
        getmxrr($domain, $mx_records); // Cache $mx_records for about 6 hours
        return !empty($mx_records);
    }

    function isRoleAddress(string $username): bool {
        global $role_based_email_keywords;
        return in_array($username, $role_based_email_keywords);
    }

    function isDisposableAddress(string $domain): bool {
        global $disposable_domain_str;
        $disposable_domains = json_decode($disposable_domain_str);
        return in_array($domain, $disposable_domains);
    }

    function isValidFormat(string $address): bool {
        return filter_var($address, FILTER_VALIDATE_EMAIL);
    }

    function isValidSMTP(string $domain, string $recipient_email): string {
        getmxrr($domain, $mx_records); // Cache $mx_records for about 6 hours

        // Loop through till a working server is found,
        // irrespective of whether the response is positive or negative.
        foreach ($mx_records as $smtp_host) {
            $result = checkSMTPServer($smtp_host, $recipient_email, 25);
            if ($result) return $result;
        }

        return 'failed';
    }    

    function checkSMTPServer(string $host, $recipient_email, int $port) {
        global $debug;
        $smtp_socket = fsockopen($host, $port, $errno, $errstr);
        if (!$smtp_socket) return false; // Connection failed, return false to try the next server in the loop.

        $welcome_response = fread($smtp_socket, 1024); if ($debug) { echo '<pre>'; var_dump($welcome_response); echo '</pre>'; }
        if (!preg_match('/^220/', $welcome_response)) { // 220:	Service ready
            // Got a response
            fclose($smtp_socket);
            return 'smtp_connection_error';
        }

        // Connected successfully, simulate outgoing email to test for deliverability
        $sender_email = '<sender@example.com>';
        $email_subject = 'Test Email';
        $email_body = "This is a test email sent via PHP and fsockopen";
        list(, $domain) = explode('@', $recipient_email);
        $message_id = md5(uniqid(rand(), true)) . "@$domain";

        // Construct the email message with headers and body
        $email_message  = "Message-Id: <$message_id>" . PHP_EOL;
        $email_message .= "From: <$sender_email>" . PHP_EOL;
        $email_message .= "To: <$recipient_email>" . PHP_EOL;
        $email_message .= "Subject: $email_subject" . PHP_EOL;
        $email_message .= PHP_EOL; // Empty line to separate headers from body
        $email_message .= $email_body . PHP_EOL;
        $email_message .= '.'; // End of email content

        $smtp_commands = [
            "HELO ". $_SERVER['SERVER_NAME'] . PHP_EOL,
            "MAIL FROM:<{$sender_email}>" . PHP_EOL,
            "RCPT TO:<{$recipient_email}>" . PHP_EOL,
            // "DATA" . PHP_EOL,
            // $email_message . PHP_EOL,
            "QUIT" . PHP_EOL
        ];

        if ($debug) stream_set_timeout($smtp_socket, 300); // 5 minutes
        
        foreach ($smtp_commands as $command) {
            fwrite($smtp_socket, $command);
            $response = fread($smtp_socket, 1024); if ($debug) echo "<b>{$command}</b> {$response}<br/>";

            // https://www.auronsoftware.com/kb/general/e-mail/list-of-smtp-response-and-error-codes/#:~:text=The%20first%20digit%20of%20an,sense%20to%20try%20again%20later.
            // https://mailtrap.io/blog/smtp-commands-and-responses/#:~:text=%40recipient.net%22-,DATA,%2C%20attachments%2C%20and%20body%20text.
            if (!preg_match('/^(250|354|221)/', $response)) {
                $socket_info = stream_get_meta_data($smtp_socket);
                if ($socket_info['timed_out']) return 'smtp_timeout';

                fclose($smtp_socket);

                if (preg_match('/^550/', $response)) {
                    if (findKeywords(['mailbox', 'unavailable', 'not exist'], $response)) {
                        return 'mailbox_does_not_exist';
                    } else {
                        if ($debug) { echo '<pre>'; var_dump($response); echo '</pre>'; }
                        return 'mailbox_unavailable';
                    }
                } elseif (preg_match('/^421/', $response)) {
                    return 'service_not_available';
                } else {
                    if ($debug) { echo '<pre>'; var_dump($response); echo '</pre>'; }
                    return 'smtp_error';
                }
            }
        }

        fclose($smtp_socket);
        return 'deliverable';
    }

    function findKeywords(array $keywords, string $string): int {
        // Escape special characters in the keywords to avoid regex issues
        $escapedKeywords = array_map('preg_quote', $keywords);
        
        // Create a regular expression pattern to match any of the keywords
        $pattern = '/\b(' . implode('|', $escapedKeywords) . ')\b/i';
        
        // Perform the search using preg_match_all
        return preg_match_all($pattern, $string, $matches);
    }

    // Entry point to validate email address
    function validateEmail(string $email): array {
        if(!isValidFormat($email)) {
            return [
                'address' => $email,
                'error' => 'Invalid email format'
            ];
        }
        
        list($local, $domain) = explode('@', $email);
        $validation = [];

        $validation['address'] = $email;
        $validation['has_mx_records'] = hasMXRecords($domain);
        $validation['is_role_address'] = isRoleAddress($local);
        $validation['is_disposable_address'] = isDisposableAddress($domain);
        $validation['is_valid_smtp'] = $validation['has_mx_records'] ? isValidSMTP($domain, $email) : 'no_mx';

        return $validation;
    }

?>