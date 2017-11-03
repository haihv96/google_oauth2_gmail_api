<?php

function listMessages($service, $userId)
{
    $pageToken = NULL;
    $messages = array();
    $opt_param = array();
    do {
        try {
            if ($pageToken) {
                $opt_param['pageToken'] = $pageToken;
            }
            $messagesResponse = $service->users_messages->listUsersMessages($userId, $opt_param);
            if ($messagesResponse->getMessages()) {
                $messages = array_merge($messages, $messagesResponse->getMessages());
                $pageToken = $messagesResponse->getNextPageToken();
            }
        } catch (Exception $e) {
            print 'An error occurred: ' . $e->getMessage();
        }
    } while ($pageToken);

    foreach ($messages as $message) {
        print 'Message with ID: ' . $message->getId() . '<br/>';
    }

    return $messages;
}

function getMessage($service, $userId, $messageId)
{
    try {
        $message = $service->users_messages->get($userId, $messageId);
        print 'Message with ID: ' . $message->getId() . ' retrieved.';
        return $message;
    } catch (Exception $e) {
        print 'An error occurred: ' . $e->getMessage();
    }
}

function listThreads($service, $userId) {
  $threads = array();
  $pageToken = NULL;
  do {
    try {
      $opt_param = array();
      if ($pageToken) {
        $opt_param['pageToken'] = $pageToken;
      }
      $threadsResponse = $service->users_threads->listUsersThreads($userId, $opt_param);
      if ($threadsResponse->getThreads()) {
        $threads = array_merge($threads, $threadsResponse->getThreads());
        $pageToken = $threadsResponse->getNextPageToken();
      }
    } catch (Exception $e) {
      print 'An error occurred: ' . $e->getMessage();
      $pageToken = NULL;
    }
  } while ($pageToken);

  foreach ($threads as $thread) {
    print 'Thread with ID: ' . $thread->getId() . '<br/>';
  }

  return $threads;
}

function getThread($service, $userId, $threadId) {
  try {
    $thread = $service->users_threads->get($userId, $threadId);
    $messages = $thread->getMessages();
    $msgCount = count($messages);
    print 'Number of Messages in the Thread: ' . $msgCount;
    return $thread;
  } catch (Exception $e){
    print 'An error occurred: ' . $e->getMessage();
  }
}

function decodeBody($body) {
    $rawData = $body;
    $sanitizedData = strtr($rawData,'-_', '+/');
    $decodedMessage = base64_decode($sanitizedData);
    if(!$decodedMessage){
        $decodedMessage = FALSE;
    }
    return $decodedMessage;
}

function getHeader($headers, $name) {
  foreach($headers as $header) {
    if($header['name'] == $name) {
      return $header['value'];
    }
  }
}
