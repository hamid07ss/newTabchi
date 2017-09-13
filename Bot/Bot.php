<?php

use Predis\Autoloader;
use Predis\Client;

class Bot {
    const RedisDbs = [
        'allLinks' => 'newTabChiAllLinks',
        'waiteLinks' => 'newTabChiWaiteLinks',
        'checkedLinks' => 'newTabChiCheckedLinks',
        'checkLinksLimit' => 'newTabChiCheckLinksLimit',
        'joinLinksLimit' => 'newTabChiJoinLinksLimit',
        'superGroups' => 'newTabChiSuperGroups',
        'updatesOffSet' => 'newTabChiUpdatesOffSet',
        'autoJoin' => 'newTabChiAutoJoin'
    ];
    public $Redis = '';
    public $MadelineProto = '';
    private $Admins = [93077939, 231812624];









    public function __construct() {
        global $MadelineProto;

        $this->Redis = new Client();
        $this->MadelineProto = $MadelineProto;
    }

    public function findLinks($text) {
        preg_match_all('/https:\/\/(?:t?|telegram)\.me\/joinchat\/(.*[\w])/', $text, $Links);
        if(count($Links) > 0 && count($Links[1]) > 0) {
            foreach($Links[1] as $link) {
                if(!$this->Redis->sismember($this::RedisDbs['allLinks'], $link)) {
                    $this->Redis->sadd($this::RedisDbs['allLinks'], $link);
                    $this->Redis->sadd($this::RedisDbs['waiteLinks'], $link);
                }
            }
        }
    }

    public function checkLink() {
        if(!$this->Redis->get($this::RedisDbs['autoJoin'])) {
            return;
        }

        $links = $this->Redis->smembers($this::RedisDbs['waiteLinks']);
        $limit = $this->Redis->get($this::RedisDbs['checkLinksLimit']);

        if(!$limit && count($links) > 0) {
            $index = 0;
            $canConti = true;
            foreach($links as $link) {
                if(!$canConti) {
                    break;
                }
                if($index >= 2) {
                    $this->Redis->setex($this::RedisDbs['checkLinksLimit'], 100, true);
                    break;
                }
                try {
                    $this->MadelineProto->messages->checkChatInvite([
                        'hash' => $link
                    ]);

                    $this->Redis->srem($this::RedisDbs['waiteLinks'], $link);
                    $this->Redis->sadd($this::RedisDbs['checkedLinks'], $link);
                }
                catch(Exception $e) {
                    print_r($e->rpc . PHP_EOL);
                    $floodLimit = preg_match('/FLOOD_WAIT_(.*)/', $e->rpc, $limitTime);
                    switch($e->rpc) {
                        case 'INVITE_HASH_EXPIRED':
                            $this->Redis->srem($this::RedisDbs['waiteLinks'], $link);

                            break;

                        case $floodLimit > 0 ? true : false:
                            $this->Redis->setex($this::RedisDbs['checkLinksLimit'], intval($limitTime[1]), true);
                            $canConti = false;

                            break;
                        default:
                            $this->Redis->srem($this::RedisDbs['waiteLinks'], $link);

                            break;
                    }
                }

                $index++;
            }
        }
    }

    public function join() {
        if(!$this->Redis->get($this::RedisDbs['autoJoin']) || $this->getSgpCount($this->MadelineProto->getChats()) >= 500) {
            return;
        }

        $links = $this->Redis->smembers($this::RedisDbs['checkedLinks']);
        $limit = $this->Redis->get($this::RedisDbs['joinLinksLimit']);

        if(!$limit && count($links) > 0) {
            $index = 0;
            $canConti = true;
            foreach($links as $link) {
                if(!$canConti) {
                    break;
                }
                if($index >= 2) {
                    $this->Redis->setex($this::RedisDbs['joinLinksLimit'], 120, true);
                    break;
                }
                try {
                    $this->MadelineProto->messages->importChatInvite([
                        'hash' => $link
                    ]);

                    $this->Redis->srem($this::RedisDbs['checkedLinks'], $link);
                }
                catch(Exception $e) {
                    print_r($e->rpc . PHP_EOL);
                    $floodLimit = preg_match('/FLOOD_WAIT_(.*)/', $e->rpc, $limitTime);
                    switch($e->rpc) {
                        case $floodLimit > 0 ? true : false:
                            $this->Redis->setex($this::RedisDbs['joinLinksLimit'], intval($limitTime[1]), true);
                            $canConti = false;

                            break;
                        default:
                            $this->Redis->srem($this::RedisDbs['checkedLinks'], $link);

                            break;

                    }
                }

                $index++;
            }
        }
    }

    public function getSgpCount($chats) {
        $SgpCount = 0;
        foreach($chats as $chat) {
            if($chat['_'] === 'channel' && $chat['megagroup']) {
                $SgpCount++;
            }
        }

        return $SgpCount;
    }

    public function getOffSet(){
        return intval($this->Redis->get($this::RedisDbs['updatesOffSet']));
    }

    public function setOffSet($update_id){
        if($this->getOffSet() < $update_id + 1){
            $this->Redis->set($this::RedisDbs['updatesOffSet'], $update_id + 1);
        }
    }

    public function getSgps($chats) {
        $Sgps = [];
        foreach($chats as $id => $chat) {
            if($chat['_'] === 'channel' && $chat['megagroup']) {
                $Sgps[] = 100 . $chat['id'];
            }
        }

        return $Sgps;
    }

    public function Forward($msgId, $from) {
        $SuperGroups = $this->getSgps($this->MadelineProto->getChats());
        foreach($SuperGroups as $SuperGroup) {
            print_r(PHP_EOL.$SuperGroup.PHP_EOL);
            try{
                $this->MadelineProto->messages->forwardMessages([
                    'from_peer' => $from,
                    'id' => [$msgId],
                    'to_peer' => -intval($SuperGroup),
                ]);
                sleep(2);
            }catch(Exception $e){
                if(isset($e->rpc)) {
                    $this->MadelineProto->messages->sendMessage([
                        'peer' => 93077939,
                        'message' => $e->rpc,
                        'parse_mode' => 'html'
                    ]);
                }
            }
        }

        $this->MadelineProto->messages->sendMessage([
            'peer' => $from,
            'message' => 'Fwd Ended' . $SuperGroup,
            'parse_mode' => 'html'
        ]);
    }

    public function isAdmin($id){
        return array_search($id, $this->Admins) > -1;

    }
    public function handleMessages($update, $update_id) {

        $chat_id = isset($update['message']['from_id'])?$update['message']['from_id']:0;
        print_r(PHP_EOL. 'Check if Admin =========> '.$chat_id .PHP_EOL);

        if($this->isAdmin($chat_id)) {
            print_r(PHP_EOL. 'This is Admin =========> '.$chat_id .PHP_EOL);
            $text = '';
            preg_match('/^say (.*[\w\s\S\n\W\d\D\r].*)/', $update['message']['message'], $sayText);

            if(count($sayText) > 0){
                $text = $sayText[1];
            }

            switch($update['message']['message']) {
                case 'Join':
                    $this->MadelineProto->messages->importChatInvite([
                        'hash' => 'BYxBs0LlFNHOqywAQSkWpg'
                    ]);
                    $text = "Joined Ok";
                    break;

                case 'start join':
                    $this->Redis->set($this::RedisDbs['autoJoin'], true);
                    $text = "Join And Check Links Started";
                    break;

                case 'stop join':
                    $this->Redis->set($this::RedisDbs['autoJoin'], false);
                    $text = "Join And Check Links Stopped";
                    break;

                case 'help':
                    $text = "<b>Bot Help \n\n" .
                        "start|stop join\n" .
                        "start and stop auto check links and join \n\n" .
                        "panel\n" .
                        "get panel of bot \n\n" .
                        "fwd super\n" .
                        "forward replied message to all super groups \n\n" .
                    "</b>";
                    break;

                case 'fwd super':
                    if(!isset($update['message']['reply_to_msg_id'])) {
                        $text = "Please Retry With Reply";
                    }
                    else {
                        $text = "Forwarded";
                        $this->Forward($update['message']['reply_to_msg_id'], $update['message']['from_id']);
                    }

                    break;

                case 'panel':
                    $text = "<b>Panel\n\n" .
                        "All Chats: " . $this->getSgpCount($this->MadelineProto->getChats()) . "\n" .
                        "All Links: " . count($this->Redis->smembers($this::RedisDbs['waiteLinks'])) . "\n" .
                        "Checked Links: " . count($this->Redis->smembers($this::RedisDbs['checkedLinks'])) . "\n\n" .

                        "Next Check: " . $this->Redis->ttl($this::RedisDbs['checkLinksLimit']) . "\n" .
                        "Next Join: " . $this->Redis->ttl($this::RedisDbs['joinLinksLimit']) . "\n" .

                        "</b>";

            }


            if($text !== '') {
                try {
                    $chat_id = $update['message']['to_id']['_'] === 'peerChannel' ?
                        -100 . $update['message']['to_id']['channel_id'] : $update['message']['from_id'];
                    print_r(-$chat_id . PHP_EOL);
                    $this->MadelineProto->messages->sendMessage([
                        'peer' => $chat_id,
                        'message' => $text,
                        'parse_mode' => 'html'
                    ]);
                }
                catch(Exception $e) {
                    if(isset($e->rpc)) {
                        print_r($e->rpc . PHP_EOL);
                    }
                }
            }
        }


        if(isset($update['message']['message'])) {
            $this->findLinks($update['message']['message']);
        }
        $this->checkLink();
        $this->join();
    }
}