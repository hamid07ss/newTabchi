---
title: account.registerDevice
description: account.registerDevice parameters, return type and example
---
## Method: account.registerDevice  
[Back to methods index](index.md)


### Parameters:

| Name     |    Type       | Required |
|----------|---------------|----------|
|token\_type|[int](../types/int.md) | Yes|
|token|[string](../types/string.md) | Yes|
|device\_model|[string](../types/string.md) | Yes|
|system\_version|[string](../types/string.md) | Yes|
|app\_version|[string](../types/string.md) | Yes|
|app\_sandbox|[Bool](../types/Bool.md) | Yes|
|lang\_code|[string](../types/string.md) | Yes|


### Return type: [Bool](../types/Bool.md)

### Can bots use this method: **NO**


### Errors this method can return:

| Error    | Description   |
|----------|---------------|
|TOKEN_INVALID|The provided token is invalid|


### Example:


```
$MadelineProto = new \danog\MadelineProto\API();
if (isset($number)) { // Login as a user
    $sentCode = $MadelineProto->phone_login($number);
    echo 'Enter the code you received: ';
    $code = '';
    for ($x = 0; $x < $sentCode['type']['length']; $x++) {
        $code .= fgetc(STDIN);
    }
    $MadelineProto->complete_phone_login($code);
}

$Bool = $MadelineProto->account->registerDevice(['token_type' => int, 'token' => 'string', 'device_model' => 'string', 'system_version' => 'string', 'app_version' => 'string', 'app_sandbox' => Bool, 'lang_code' => 'string', ]);
```

Or, if you're using the [PWRTelegram HTTP API](https://pwrtelegram.xyz):



### As a user:

POST/GET to `https://api.pwrtelegram.xyz/userTOKEN/account.registerDevice`

Parameters:

token_type - Json encoded int

token - Json encoded string

device_model - Json encoded string

system_version - Json encoded string

app_version - Json encoded string

app_sandbox - Json encoded Bool

lang_code - Json encoded string




Or, if you're into Lua:

```
Bool = account.registerDevice({token_type=int, token='string', device_model='string', system_version='string', app_version='string', app_sandbox=Bool, lang_code='string', })
```
