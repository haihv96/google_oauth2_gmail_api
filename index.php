<?php
// include your composer dependencies
require_once 'vendor/autoload.php';
require_once 'method.php';

define("CALLBACK_URL", "http://localhost/project_php/google-api-php-client/");
define("HOST", "127.0.0.1");
define("USER", "root");
define("PASSWORD", "hoanghai096");
define("DB", "gmail_api");

$conn = mysqli_connect(HOST, USER, PASSWORD, DB);
$conn->set_charset("utf8");

$client = new Google_Client();
$client->setAuthConfig('client_secret_284065980376-o02nqvcf8qag4ea8u1q4qfik7dugnegk.apps.googleusercontent.com.json');
$client->addScope(Google_Service_Gmail::GMAIL_READONLY);
$client->setRedirectUri(CALLBACK_URL);

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code'])['access_token'];
    $client->setAccessToken($token);

    $gmail = new Google_Service_Gmail($client);

    $list = $gmail->users_messages->listUsersMessages('me', ['maxResults' => 10]);
    try {
        while ($list->getMessages() != null) {

            foreach ($list->getMessages() as $mlist) {

                $message_id = $mlist->id;
                $optParamsGet2['format'] = 'full';
                $single_message = $gmail->users_messages->get('me', $message_id, $optParamsGet2);
                $payload = $single_message->getPayload();
                $parts = $payload->getParts();
                $body = $payload->getBody();
                $headers = $payload->getHeaders();
                $FOUND_BODY = FALSE;
                if (!$FOUND_BODY) {
                    foreach ($parts as $part) {
                        if ($part['parts'] && !$FOUND_BODY) {
                            foreach ($part['parts'] as $p) {
                                if ($p['parts'] && count($p['parts']) > 0) {
                                    foreach ($p['parts'] as $y) {
                                        if (($y['mimeType'] === 'text/html') && $y['body']) {
                                            $FOUND_BODY = decodeBody($y['body']->data);
                                            break;
                                        }
                                    }
                                } else if (($p['mimeType'] === 'text/html') && $p['body']) {
                                    $FOUND_BODY = decodeBody($p['body']->data);
                                    break;
                                }
                            }
                        }
                        if ($FOUND_BODY) {
                            break;
                        }
                    }
                }

                if ($FOUND_BODY && count($parts) > 1) {
                    $images_linked = array();
                    foreach ($parts as $part) {
                        if ($part['filename']) {
                            array_push($images_linked, $part);
                        } else {
                            if ($part['parts']) {
                                foreach ($part['parts'] as $p) {
                                    if ($p['parts'] && count($p['parts']) > 0) {
                                        foreach ($p['parts'] as $y) {
                                            if (($y['mimeType'] === 'text/html') && $y['body']) {
                                                array_push($images_linked, $y);
                                            }
                                        }
                                    } else if (($p['mimeType'] !== 'text/html') && $p['body']) {
                                        array_push($images_linked, $p);
                                    }
                                }
                            }
                        }
                    }

                    preg_match_all('/wdcid(.*)"/Uims', $FOUND_BODY, $wdmatches);
                    if (count($wdmatches)) {
                        $z = 0;
                        foreach ($wdmatches[0] as $match) {
                            $z++;
                            if ($z > 9) {
                                $FOUND_BODY = str_replace($match, 'image0' . $z . '@', $FOUND_BODY);
                            } else {
                                $FOUND_BODY = str_replace($match, 'image00' . $z . '@', $FOUND_BODY);
                            }
                        }
                    }

                    preg_match_all('/src="cid:(.*)"/Uims', $FOUND_BODY, $matches);
                    if (count($matches)) {
                        $search = array();
                        $replace = array();
                        // let's trasnform the CIDs as base64 attachements
                        foreach ($matches[1] as $match) {
                            foreach ($images_linked as $img_linked) {
                                foreach ($img_linked['headers'] as $img_lnk) {
                                    if ($img_lnk['name'] === 'Content-ID' || $img_lnk['name'] === 'Content-Id' || $img_lnk['name'] === 'X-Attachment-Id') {
                                        if ($match === str_replace('>', '', str_replace('<', '', $img_lnk->value))
                                            || explode("@", $match)[0] === explode(".", $img_linked->filename)[0]
                                            || explode("@", $match)[0] === $img_linked->filename
                                        ) {
                                            $search = "src=\"cid:$match\"";
                                            $mimetype = $img_linked->mimeType;
                                            $attachment = $gmail->users_messages_attachments->get('me', $mlist->id, $img_linked['body']->attachmentId);
                                            $data64 = strtr($attachment->getData(), array('-' => '+', '_' => '/'));
                                            $replace = "src=\"data:" . $mimetype . ";base64," . $data64 . "\"";
                                            $FOUND_BODY = str_replace($search, $replace, $FOUND_BODY);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                // If we didn't find the body in the last parts,
                // let's loop for the first parts (text-html only)
                if (!$FOUND_BODY) {
                    foreach ($parts as $part) {
                        if ($part['body'] && $part['mimeType'] === 'text/html') {
                            $FOUND_BODY = decodeBody($part['body']->data);
                            break;
                        }
                    }
                }
                // With no attachment, the payload might be directly in the body, encoded.
                if (!$FOUND_BODY) {
                    $FOUND_BODY = decodeBody($body['data']);
                }
                // Last try: if we didn't find the body in the last parts,
                // let's loop for the first parts (text-plain only)
                if (!$FOUND_BODY) {
                    foreach ($parts as $part) {
                        if ($part['body']) {
                            $FOUND_BODY = decodeBody($part['body']->data);
                            break;
                        }
                    }
                }
                if (!$FOUND_BODY) {
                    $FOUND_BODY = '(No message)';
                }
                // Finally, print the message ID and the body
                $headersObject = [];
                $fieldList = ["Delivered-To", "Received", "X-Received", "Return-Path", "Received",
                    "Received-SPF", "Authentication-Results", "Received", "X-Received", "X-Received",
                    "Return-Path", "Received", "Received-SPF", "Received", "DKIM-Signature",
                    "MIME-Version", "X-Received", "X-No-Auto-Attachment", "Message-ID", "Date",
                    "Subject", "From", "To", "Content-Type"];
                foreach ($fieldList as $field) {
                    $headerObject[$field] = getHeader($headers, $field);
                }

                $subject = getHeader($headers, 'Subject');
                $headersObject = json_encode($headerObject);
                $conn->query("INSERT INTO gmail(subject, raw_body, headers)  
                  VALUES ('$subject', '$FOUND_BODY', '$headersObject')");
            }

            if ($list->getNextPageToken() != null) {
                $pageToken = $list->getNextPageToken();
                $list = $gmail->users_messages->listUsersMessages('me', ['pageToken' => $pageToken, 'maxResults' => 1000]);
            } else {
                break;
            }
        }
    } catch (Exception $e) {
        echo $e->getMessage();
    }
}

$authUrl = $client->createAuthUrl();

$output = '<a href="' . filter_var($authUrl, FILTER_SANITIZE_URL) . '"><h1>click</h1></a>';

?>

<div><?php echo $output; ?></div>
