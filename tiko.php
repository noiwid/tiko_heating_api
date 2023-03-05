<?
//===================================================================================================
// TIKO API GATEWAY
// This component allowing to manage traditional radiators connected via the TIKO solution from within Home Assistant server
//---------------------------------------------------------------------------------------------------
// This program has two main functions:
// a) It'll help to setup Tiko package in your home assistant, by generating tiko.yaml file + related cards
// b) Serve as endpoint between your Home Assistant and Tiko's API (to update sensors, send commands, etc.)
// This program need to be hosted for this purpose.
// 
// After first use the script will create a tiko.env file in the same directory with credentials & endpoint URL.
//---------------------------------------------------------------------------------------------------
// To launch install, go to :
// https://www.yourdomain.com/tiko.php
// If you need to reinstall the H.A package, you can access the H.A setup page like this :
// https://www.yourdomain.com/tiko.php?install=true&hash=ENDPOINT_TOKEN (replace ENDPOINT_TOKEN with value found in tiko.env)
//---------------------------------------------------------------------------------------------------
// release date : 2023-03-04
//===================================================================================================

// Load environnement variables & credentials
$currentFolder = dirname(__FILE__).DIRECTORY_SEPARATOR;
$config = parse_ini_file($currentFolder.'tiko.env', true);

if ($_REQUEST['enr_ok']) {
   $randomtoken = bin2hex(random_bytes(32));

   // Get current URL
   $url = 'http' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

   // Remove params from URL
   $url_without_params = strtok($url, '?');

   // Remove filename to only keep script name
   $script_url = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $url_without_params);

  // Get datas from forml 
  $config['tiko_credentials']['TIKO_EMAIL'] = $_POST['email'];
  $config['tiko_credentials']['TIKO_PASSWORD'] = $_POST['password'];
  // Token aléatoire
  $config['tiko_endpoint']['ENDPOINT_TOKEN'] = $randomtoken;
  // URL du endpoint
  $config['tiko_endpoint']['ENDPOINT_URL'] = $script_url;

  $config_string = '';
   foreach ($config as $section => $values) {
         $config_string .= "[$section]\n";
         foreach ($values as $key => $value) {
             $config_string .= "$key='$value'\n";
         }
         $config_string .= "\n";
     }
  $put = file_put_contents($currentFolder.'tiko.env', $config_string);

  // Config file is now created, redirect user on setup page
  header('Location: tiko.php?install=true&hash='.$randomtoken);
  exit;
}
// If credentials's missing, ask to fill them again
if (!isset($config['tiko_credentials']['TIKO_EMAIL']) || !isset($config['tiko_credentials']['TIKO_PASSWORD'])) {
   f_settings();
    
}
else {
   $tiko_email = $config['tiko_credentials']['TIKO_EMAIL'];
   $tiko_password = $config['tiko_credentials']['TIKO_PASSWORD'];
   $hash = $config['tiko_endpoint']['ENDPOINT_TOKEN'];
   $baseurl = $config['tiko_endpoint']['ENDPOINT_URL']."?hash=".$hash;
}
   
///////////////
// FUNCTION
//////////////
if(($hash and $_REQUEST["hash"]==$hash) or $_REQUEST["install"]){
   function f_tiko($json, $token=false, $account_id=false){
      if(!$account_id) {
         $url = "https://particuliers-tiko.fr/api/v3/graphql/";
         $method = "POST";
      }
      else {
         $url = "https://particuliers-tiko.fr/api/v3/properties/".$account_id."/consumption_summary/";
         $method = "GET";
      }
      $headers = array(
         'Content-Type:application/json',
         // 'User-agent:Mozilla/5.0 (Linux; Android 13; Pixel 4a Build/T1B3.221003.003; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/106.0.5249.126 Mobile Safari/537.36' // needed with tiko.ch api endpoint
        );
      if($token)
         $headers[] = 'Authorization: token '.$token; 

      $chObj = curl_init();
      curl_setopt($chObj, CURLOPT_SSL_VERIFYPEER, FALSE); // needed localy with wamp
      curl_setopt($chObj, CURLOPT_URL, $url);
      curl_setopt($chObj, CURLOPT_FRESH_CONNECT, TRUE);
      curl_setopt($chObj, CURLOPT_RETURNTRANSFER, true);    
      curl_setopt($chObj, CURLOPT_CUSTOMREQUEST, $method);
      curl_setopt($chObj, CURLOPT_POSTFIELDS, $json);
      curl_setopt($chObj, CURLOPT_HTTPHEADER, $headers); 
      $json = curl_exec($chObj);
      curl_close($chObj);
      return json_decode($json,true);
   }

   /////////////
   // LOGIN 
   /////////////
      $json = '{ 
         "variables":{
            "email":"'.$tiko_email.'",
            "password":"'.$tiko_password.'",
            "langCode": "fr",
            "retainSession": true
         },
         "query":"mutation LogIn($email: String!, $password: String!, $langCode: String, $retainSession: Boolean) {\n  logIn(\n    input: {email: $email, password: $password, langCode: $langCode, retainSession: $retainSession}\n  ) {\n    settings {\n      client {\n        name\n        __typename\n      }\n      support {\n        serviceActive\n        phone\n        email\n        __typename\n      }\n      __typename\n    }\n    user {\n      id\n      clientCustomerId\n      agreements\n      properties {\n        id\n        allInstalled\n        __typename\n      }\n      inbox(modes: [\"app\"]) {\n        actions {\n          label\n          type\n          value\n          __typename\n        }\n        id\n        lockUser\n        maxNumberOfSkip\n        messageBody\n        messageHeader\n        __typename\n      }\n      __typename\n    }\n    token\n    firstLogin\n    __typename\n  }\n}\n"
      }';
      $login = f_tiko($json);  

      // get account_id & token in login feedback
      $account_id = $login["data"]["logIn"]["user"]["properties"][0]["id"];
      $token = $login["data"]["logIn"]["token"];
      // identification problem? resend credential form
      if(!$token)
         f_settings();

   /*********************
   * 
   * GET DATAS 
   * 
   **********************/

   /*******************************
   * GET GLOBAL ENERGY CONSUMPTION
   *******************************/
   if(!$_REQUEST["room_id"] and !isset($_REQUEST["mode"]) and $_REQUEST["consumption"]){
      $datas = f_tiko(false, $token, $account_id);
      $feedback = $datas["response"];
      $logs = file_put_contents($serv_root.$prod."/include/logs/".date("Ymd-His")."-getDatas.log", print_r($feedback, true));
   }

   /**********************************
   * GET heaters datas + global modes
   ***********************************/
   elseif(!$_REQUEST["room_id"] and !isset($_REQUEST["mode"]) and !isset($_REQUEST["install"])){
      $json = '{
         "operationName":"GET_PROPERTY_OVERVIEW_DECENTRALISED",
         "variables":{ "id":'.$account_id.' },
         "query":"query GET_PROPERTY_OVERVIEW_DECENTRALISED($id: Int!, $excludeRooms: [Int]) {\n  settings {\n    benchmark {\n      isEnabled\n      __typename\n    }\n    __typename\n  }\n  property(id: $id) {\n    id\n    mode\n    mboxDisconnected\n    isNetatmoAuthorised\n    netatmoLinkAccountUrl\n    isSinapsiEnabled\n    isSinapsiAuthorised\n    allInstalled\n    ownerPermission\n    constructionYear\n    surfaceArea\n    floors\n    valueProposition\n    address {\n      id\n      street\n      number\n      city\n      zipCode\n      __typename\n    }\n    tips {\n      id\n      tip\n      __typename\n    }\n    ...CentralisedDevicesCompact\n    rooms(excludeRooms: $excludeRooms) {\n      id\n      name\n      type\n      color\n      heaters\n      hasTemperatureSchedule\n      currentTemperatureDegrees\n      targetTemperatureDegrees\n      humidity\n      sensors\n      devices {\n        id\n        code\n        type\n        name\n        mac\n        __typename\n      }\n      ...Status\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment CentralisedDevicesCompact on PropertyType {\n  devices(excludeDecentralised: true) {\n    id\n    code\n    type\n    name\n    mac\n    __typename\n  }\n  externalDevices {\n    id\n    name\n    __typename\n  }\n  __typename\n}\n\nfragment Status on RoomType {\n  status {\n    disconnected\n    heaterDisconnected\n    heatingOperating\n    sensorBatteryLow\n    sensorDisconnected\n    temporaryAdjustment\n    __typename\n  }\n  __typename\n}"
      }';
      $rooms = f_tiko($json, $token);  
      $modes = $rooms["data"]["property"]["mode"];
      foreach($modes as $k=>$v){
         $feedback[$k] = $v?true:false;
      }
      //$feedback["settings"] = $rooms["data"]["property"]["mode"];
      foreach($rooms["data"]["property"]["rooms"] as $k=>$v){
         $feedback[str_replace("-","",clean($v["name"]))."_cur"] = $v["currentTemperatureDegrees"];
         $feedback[str_replace("-","",clean($v["name"]))."_tar"] = $v["targetTemperatureDegrees"];
         $feedback[str_replace("-","",clean($v["name"]))."_dry"] = $v["humidity"];
         $feedback[str_replace("-","",clean($v["name"]))."_on"] = $v["status"]["heatingOperating"]?true:false;
      }
   }

   /*********************
   * 
   * SET DATAS 
   * 
   **********************/
      /**************************
       * CHANGE ROOM TEMPERATURE
       *
       * @param int $room_id target room
       * @param decimal $temperature target temperature
       * @return boolean
       **************************/
      if($_REQUEST["room_id"] and $_REQUEST["temperature"]>0){
          $json = '{ 
               "variables":{
                  "propertyId": '.$account_id.',
                  "roomId":'.$_REQUEST["room_id"].',
                  "temperature":'.$_REQUEST["temperature"].'
               },
               "query":"mutation SET_PROPERTY_ROOM_ADJUST_TEMPERATURE($propertyId: Int!, $roomId: Int!, $temperature: Float!) {\n  setRoomAdjustTemperature(\n    input: {propertyId: $propertyId, roomId: $roomId, temperature: $temperature}\n  ) {\n    id\n    adjustTemperature {\n      active\n      endDateTime\n      temperature\n      __typename\n    }\n    __typename\n  }\n}"
            }';
            $temperature = f_tiko($json, $token);  
            if($temperature["data"]["setRoomAdjustTemperature"]) 
               $feedback["status"]=true;
            else 
               $feedback["status"]=false;

         $logs = file_put_contents($serv_root.$prod."/include/logs/".date("Ymd-His")."-changeRoomTemp_".$_REQUEST["room_id"].".log", print_r($feedback, true));
   }

      /*********************************
       * CHANGE GLOBAL MODE
       *
       * @param enum $mode 
       *     frost
       *     boost
       *     absence
       *     disableHeating
       * @return boolean
       *********************************/
      if(isset($_REQUEST["mode"])){
         if($_REQUEST["mode"]) $mode = $_REQUEST["mode"];
         else $mode = "false";
          $json = '{ 
               "variables":{
                  "propertyId": '.$account_id.',
                  "mode":"'.$mode.'"
               },
               "query":"mutation SET_PROPERTY_MODE($propertyId: Int!, $mode: String!) {\n  setPropertyMode(input: {propertyId: $propertyId, mode: $mode}) {\n    id\n    mode\n    __typename\n  }\n}"
            }';
            $feedback_mode = f_tiko($json, $token);  
            $feedback["mode"]=$mode!="false"?$mode:false;
            if($feedback_mode["data"]["setPropertyMode"]) 
               $feedback["status"]=true;
            else 
               $feedback["status"]=false;
         $logs = file_put_contents($serv_root.$prod."/include/logs/".date("Ymd-His")."-changeMode_".$mode.".log", $_SERVER['REMOTE_ADDR']."\n".$_SERVER['HTTP_USER_AGENT']."\n".basename($_SERVER['REQUEST_URI'])."\n\n".print_r($feedback, true));
      }
      /*****************************************
       * INSTALLER, generate the tiko.yaml file
      *****************************************/
      if($_REQUEST["install"]){
         require($serv_root.$prod."/include/Spyc.php");

         $json = '{
            "operationName":"GET_PROPERTY_OVERVIEW_DECENTRALISED",
            "variables":{ "id":'.$account_id.' },
            "query":"query GET_PROPERTY_OVERVIEW_DECENTRALISED($id: Int!, $excludeRooms: [Int]) {\n  settings {\n    benchmark {\n      isEnabled\n      __typename\n    }\n    __typename\n  }\n  property(id: $id) {\n    id\n    mode\n    mboxDisconnected\n    isNetatmoAuthorised\n    netatmoLinkAccountUrl\n    isSinapsiEnabled\n    isSinapsiAuthorised\n    allInstalled\n    ownerPermission\n    constructionYear\n    surfaceArea\n    floors\n    valueProposition\n    address {\n      id\n      street\n      number\n      city\n      zipCode\n      __typename\n    }\n    tips {\n      id\n      tip\n      __typename\n    }\n    ...CentralisedDevicesCompact\n    rooms(excludeRooms: $excludeRooms) {\n      id\n      name\n      type\n      color\n      heaters\n      hasTemperatureSchedule\n      currentTemperatureDegrees\n      targetTemperatureDegrees\n      humidity\n      sensors\n      devices {\n        id\n        code\n        type\n        name\n        mac\n        __typename\n      }\n      ...Status\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment CentralisedDevicesCompact on PropertyType {\n  devices(excludeDecentralised: true) {\n    id\n    code\n    type\n    name\n    mac\n    __typename\n  }\n  externalDevices {\n    id\n    name\n    __typename\n  }\n  __typename\n}\n\nfragment Status on RoomType {\n  status {\n    disconnected\n    heaterDisconnected\n    heatingOperating\n    sensorBatteryLow\n    sensorDisconnected\n    temporaryAdjustment\n    __typename\n  }\n  __typename\n}"
         }';
         $rooms = f_tiko($json, $token);  

         $my_sensors[] = "boost";
         $my_sensors[] = "frost";
         $my_sensors[] = "absence";
         $my_sensors[] = "disableHeating";
         if(is_array($rooms))
            foreach($rooms["data"]["property"]["rooms"] as $k=>$v){
               $heaters[$v["id"]] = trim($v["name"]);
               $my_sensors[] = clean($v["name"])."_cur";
               $my_sensors[] = clean($v["name"])."_tar";
               $my_sensors[] = clean($v["name"])."_dry";
               $my_sensors[] = clean($v["name"])."_on";
            }

         if(is_array($heaters))
            foreach($heaters as $k=>$v){
               $array["tiko"]["climate"][] = array(
                  "platform"=>"generic_thermostat",
                  "name"=>$v,
                  "heater"=>"switch.radiateurs_off",
                  "target_sensor"=>"sensor.".clean($v)."_temperature"
               );
               $array["tiko"]["shell_command"][clean($v)."_set_temp"] = '/usr/bin/curl -X POST '.$baseurl.'&room_id='.$k.'&temperature={{ state_attr("climate.'.clean($v).'", "temperature") }}';
               $array["tiko"]["automation"][] = array(
                  "id"=>"sync_status_on_".clean($v),
                  "alias"=>"sync_status_on_".clean($v),
                  "description"=>"on H.A startup or heater status change, check if heater is currently on to update the climate object in HA",
                  "trigger"=>array(
                     array(
                        "platform"=>"homeassistant",
                        "event"=>"start"
                     ),
                     array(
                        "platform"=>"state",
                        "entity_id"=>"binary_sensor.".clean($v)."_chauffage"
                     ),
                  ),
                  "condition"=>array(
                     array(
                        "condition"=>"state",
                        "entity_id"=>"binary_sensor.".clean($v)."_chauffage",
                        "state"=>"on"
                     ),
                  ),
                  "action"=>array(
                     array(
                        "service"=>"climate.turn_on",
                        "target"=>array("entity_id"=>"climate.".clean($v))
                     ),

                  ),
                  "mode"=>"single",
               );
               $array["tiko"]["automation"][] = array(
                  "id"=>"sync_status_off_".clean($v),
                  "alias"=>"sync_status_off_".clean($v),
                  "description"=>"on H.A startup or heater status change, check if heater is currently off to update the climate object in HA",
                  "trigger"=>array(
                     array(
                        "platform"=>"homeassistant",
                        "event"=>"start"
                     ),
                     array(
                        "platform"=>"state",
                        "entity_id"=>"binary_sensor.".clean($v)."_chauffage"
                     ),
                  ),
                  "condition"=>array(
                     array(
                        "condition"=>"state",
                        "entity_id"=>"binary_sensor.".clean($v)."_chauffage",
                        "state"=>"off"
                     ),
                  ),
                  "action"=>array(
                     array(
                        "service"=>"climate.turn_off",
                        "target"=>array("entity_id"=>"climate.".clean($v))
                     ),

                  ),
                  "mode"=>"single",
               );
               $array["tiko"]["automation"][] = array(
                  "id"=>"sync_temp_".clean($v),
                  "alias"=>"sync_temp_".clean($v),
                  "description"=>"on H.A startup or temp change, update the climate object in HA",
                  "trigger"=>array(
                     array(
                        "platform"=>"homeassistant",
                        "event"=>"start"
                     ),
                     array(
                        "platform"=>"state",
                        "entity_id"=>"sensor.".clean($v)."_temperature_target"
                     ),
                  ),
                  "condition"=>array(),
                  "action"=>array(
                     array(
                        "service"=>"climate.set_temperature",
                        "target"=>array("entity_id"=>"climate.".clean($v)),
                        "data"=>array("temperature"=>"{{ states('sensor.".clean($v)."_temperature_target') }}")
                     ),

                  ),
                  "mode"=>"single",
               );
               $array["tiko"]["automation"][] = array(
                  "id"=>"set_temp_".clean($v),
                  "alias"=>"set_temp_".clean($v),
                  "description"=>"on climate update, send update command to endpoint",
                  "trigger"=>array(
                     array(
                        "platform"=>"state",
                        "entity_id"=>array("climate.".clean($v)),
                        "attribute"=>"temperature"
                     ),
                 ),
                  "condition"=>array(
                     array("condition"=>"and","conditions"=>array(
                        array(
                           "condition"=>"state",
                           "entity_id"=>"switch.radiateurs_off",
                           "state"=>"off"
                        ),
                        array(
                           "condition"=>"state",
                           "entity_id"=>"switch.radiateurs_horsgel",
                           "state"=>"off"
                        ),
                        array(
                           "condition"=>"state",
                           "entity_id"=>"switch.radiateurs_absence",
                           "state"=>"off"
                        ),
                     ) ),
                  ),
                  "action"=>array(
                     array(
                        "service"=>"shell_command.".clean($v)."_set_temp",
                     ),

                  ),
                  "mode"=>"single",

               );
               $array["tiko"]["sensor"][] = array(
                  "platform"=>"template",
                  "sensors"=>array(
                     clean($v)."_temperature" => array(
                        "friendly_name" => $v." temperature",
                        "value_template" => "{{ state_attr('sensor.tiko_settings','".clean($v)."_cur')}}",
                        "unit_of_measurement" => "°C",
                        "device_class" => "temperature"
                     )
                  )
               );
               $array["tiko"]["sensor"][] = array(
                  "platform"=>"template",
                  "sensors"=>array(
                     clean($v)."_temperature_target" => array(
                        "friendly_name" => $v." temperature target",
                        "value_template" => "{{ state_attr('sensor.tiko_settings','".clean($v)."_tar')}}",
                        "unit_of_measurement" => "°C",
                        "device_class" => "temperature"
                     )
                  )
               );

               $array["tiko"]["binary_sensor"][] = array(
                  "platform"=>"template",
                  "sensors"=>array(
                     clean($v)."_chauffage" => array(
                        "friendly_name" => $v." chauffage",
                        "value_template" => "{{ is_state_attr('sensor.tiko_settings','".clean($v)."_on', true)}}",
                        "device_class" => "heat"
                     )
                  )
               );
            } // end foreach
            $array["tiko"]["sensor"][] = array(
               "platform"=>"command_line",
               "name"=>"Tiko_consumption",
               "json_attributes"=>array(
                  0 => "today_total_wh",
                  1 => "yesterday_total_same_time_wh",
                  2 => "yesterday_total_same_time_wh",
                  3 => "last_month_total_wh",
                  4 => "this_month_total_wh",
                  5 => "last_month_total_same_day_wh"
               ),
               "command" => "curl -s '".$baseurl."&consumption=true'",
               "unit_of_measurement" => "W",
               "scan_interval" => 3600,
               "value_template" => 1
            );
            $array["tiko"]["sensor"][] = array(
               "platform"=>"command_line",
               "name"=>"Tiko_settings",
               "json_attributes"=> 
                  $my_sensors,
               "command" => "curl -s '".$baseurl."'",
               "scan_interval" => 60,
               "value_template" => 1
            );


            $array["tiko"]["switch"] = array(
               array(
                  "platform"=>"command_line",
                  "switches"=>array(
                     "radiateurs_on_off"=>array(
                        "friendly_name"=>"Radiateurs on/off",
                        "command_on"=>"curl -g '".$baseurl."&mode=0'",
                        "command_off"=>"curl -g '".$baseurl."&mode=disableHeating'",
                        "command_state"=>"curl -g '".$baseurl."'",
                        "value_template"=>'{{value_json["disableHeating"]}}',
                        "icon_template"=>"{% if (value_json.disableHeating) %} mdi:radiator-off {% else %} mdi:radiator-off {% endif %}",
                     )
                  )
               ),
               array(
                  "platform"=>"command_line",
                  "switches"=>array(
                     "radiateurs_off"=>array(
                        "friendly_name"=>"Radiateurs off",
                        "command_on"=>"curl -g '".$baseurl."&mode=disableHeating'",
                        "command_off"=>"curl -g '".$baseurl."&mode=0'",
                        "command_state"=>"curl -g '".$baseurl."'",
                        "value_template"=>'{{value_json["disableHeating"]}}',
                        "icon_template"=>"{% if (value_json.disableHeating) %} mdi:radiator-off {% else %} mdi:radiator-off {% endif %}",
                     )
                  )
               ),
               array(
                  "platform"=>"command_line",
                  "switches"=>array(
                     "radiateurs_boost"=>array(
                        "friendly_name"=>"Radiateurs boost",
                        "command_on"=>"curl -g '".$baseurl."&mode=boost²'",
                        "command_off"=>"curl -g '".$baseurl."&mode=0'",
                        "command_state"=>"curl -g '".$baseurl."'",
                        "value_template"=>'{{value_json["boost"]}}',
                        "icon_template"=>"{% if (value_json.boost) %} mdi:sun-thermometer {% else %} mdi:lightning-bolt-outline {% endif %}",
                     )
                  )
               ),
               array(
                  "platform"=>"command_line",
                  "switches"=>array(
                     "radiateurs_absence"=>array(
                        "friendly_name"=>"Radiateurs absence",
                        "command_on"=>"curl -g '".$baseurl."&mode=absence'",
                        "command_off"=>"curl -g '".$baseurl."&mode=0'",
                        "command_state"=>"curl -g '".$baseurl."'",
                        "value_template"=>'{{value_json["absence"]}}',
                        "icon_template"=>"{% if (value_json.absence) %} mdi:door-closed-lock {% else %} mdi:door {% endif %}",
                     )
                  )
               ),
               array(
                  "platform"=>"command_line",
                  "switches"=>array(
                     "radiateurs_horsgel"=>array(
                        "friendly_name"=>"Radiateurs hors gel",
                        "command_on"=>"curl -g '".$baseurl."&mode=frost'",
                        "command_off"=>"curl -g '".$baseurl."&mode=0'",
                        "command_state"=>"curl -g '".$baseurl."'",
                        "value_template"=>'{{value_json["frost"]}}',
                        "icon_template"=>"{% if (value_json.frost) %} mdi:snowflake-thermometer {% else %} mdi:snowflake-thermometer {% endif %}",
                     )
                  )
               )
            );
            ////////////////////////////////////
            // Setup lovelace cards
            ////////////////////////////////////
            $lovelace = array(
               'type' => 'custom:vertical-stack-in-card',
               'cards' => array(
               array(
                  'type' => 'entities',
                  'entities' => array(
                      array(
                          'entity' => 'switch.radiateurs_off',
                          'state_color' => true,
                          'name' => 'Arrêt chauffage'
                      ),
                      array(
                          'entity' => 'switch.radiateurs_horsgel',
                          'state_color' => true,
                          'name' => 'Mode Hors gel'
                      ),
                      array(
                          'entity' => 'switch.radiateurs_boost',
                          'state_color' => true,
                          'name' => 'Mode Boost'
                      ),
                      array(
                          'entity' => 'switch.radiateurs_absence',
                          'state_color' => true,
                          'name' => 'Mode Absence'
                      )
                  ),
                  'show_header_toggle' => false
               ),
               array(
                  'type' => 'divider'
               )
            )
         );

         // Loop to add entity from rooms
         foreach($rooms["data"]["property"]["rooms"] as $k=>$v){
             $lovelace['cards'][] = array(
                 'type' => 'divider'
             );
             $lovelace['cards'][] = array(
                 'type' => 'custom:better-thermostat-ui-card',
                 'name' => $v['name'],
                 'entity' => "climate.".clean($v['name']),
                 'eco_temperature' => 5,
                 'disable_window' => true,
                 'disable_summer' => true,
                 'disable_heat' => true,
                 'disable_eco' => true,
                 'disable_off' => true,
                 'set_current_as_main' => false
             );
         }
         $lovelace['cards'][] = array(
           'type' => 'divider'
          );
         $lovelace['cards'][] = array(
            'type' => 'entities',
            'show_header_toggle' => false,
            'entities' => array(
                array(
                    'entity' => 'sensor.tiko_consumption',
                    'name' => "Aujourd'hui",
                    'icon' => 'mdi:calendar-today',
                    'type' => 'attribute',
                    'attribute' => 'today_total_wh',
                    'suffix' => 'W'
                ),
                array(
                    'entity' => 'sensor.tiko_consumption',
                    'name' => 'Hier à la même heure',
                    'icon' => 'mdi:calendar-today-outline',
                    'type' => 'attribute',
                    'attribute' => 'yesterday_total_same_time_wh',
                    'suffix' => 'W'
                ),
                array(
                    'entity' => 'sensor.tiko_consumption',
                    'name' => 'Ce mois ci',
                    'icon' => 'mdi:calendar-month',
                    'type' => 'attribute',
                    'attribute' => 'this_month_total_wh',
                    'suffix' => 'W'
                ),
                array(
                    'entity' => 'sensor.tiko_consumption',
                    'name' => 'Le mois dernier',
                    'icon' => 'mdi:calendar-month-outline',
                    'type' => 'attribute',
                    'attribute' => 'last_month_total_wh',
                    'suffix' => 'W'
                ),
                array(
                    'entity' => 'sensor.tiko_consumption',
                    'name' => 'Le même jour le mois dernier',
                    'icon' => 'mdi:compare-horizontal',
                    'type' => 'attribute',
                    'attribute' => 'last_month_total_same_day_wh',
                    'suffix' => 'W'
                )
            )
         );?>
         <h1 style="margin-bottom:10px">Pour installer le package TIKO</h1>
         <div style="margin-bottom: 15px">Via le <strong>File editor</strong> :</div>
            <ol class="border">
              <li>Assurez vous que la ligne suivante soit présente dans la section <strong>homeassistant:</strong> dans votre fichier <strong>config/configuration.yaml</strong> :
                 <div class="code">packages: !include_dir_merge_named packages/</div>
                 Si ce n'est pas le cas, ajoutez la.

              </li>
              <li>Créez un dossier pour le package<br/>
                 <div class="code">config/packages/<strong>tiko</strong></div>
                 Si nécessaire, créez également le dossier <strong>packages/</strong>
              </li>
              <li>Créez dans le dossier <strong>tiko</strong> un fichier <strong>tiko.yaml</strong><br/>
                 <div class="code">config/packages/tiko/<strong>tiko.yaml</strong></div>
              </li>
              <li>Collez ce code dans le fichier <strong>tiko.yaml</strong><br/>
                 <textarea style="margin: 10px 0 0;width: 100%; height:350px"><?=spyc_dump($array);?></textarea>
              </li>
              <li>Depuis HACS > Frontend, rajoutez les dépendances sur lesquelles se reposent les cartes 
               <div style="margin:5px 0">
                  - <strong>Better Thermostat UI :</strong> <a href="https://github.com/KartoffelToby/better-thermostat-ui-card">https://github.com/KartoffelToby/better-thermostat-ui-card</a></a>                     
               </div>
               <div style="margin:5px 0">
                  - <strong>Vertical Stack In Card : </strong> <a href="https://mars.decrocher-la-lune.com:8123/hacs/repository/439367892">https://mars.decrocher-la-lune.com:8123/hacs/repository/439367892</a>
               </div>
              </li>
              <li>Pour gérer vos radiateurs depuis votre dashboard lovelace, editez votre dashboard puis ajoutez une carte en mode manuel, et collez le code suivant dans l'éditeur de cartes :</strong><br/>
                 <textarea style="margin: 10px 0 0;width: 100%; height:280px"><?=spyc_dump($lovelace);?></textarea>
              </li>
              <li>Redémarrez home assistant :<br/>
                 <div style="margin:3px 0">
                    - Menu <strong>Settings</strong> > <strong>System</strong>, puis le bouton <strong>RESTART</strong> se trouve dans le coin haut droite de la page
                  </div>
              </li>
            </ol>
            <style>
             body { font-family:tahoma }
             .code { padding:4; margin: 4px 0; background-color:#EEE; font-family:courier }
               ol.border {
                list-style-type: none;
                list-style-type: decimal !ie;     
                margin: 0;
                margin-left: 3em;
                padding: 0;     
                counter-reset: li-counter;
            }

            ol.border > li{
                position: relative;
                margin-bottom: 20px;
                padding-left: 0.5em;
                min-height: 3em;
                border-left: 2px solid #CCCCCC;
            }

            ol.border > li:before {
                position: absolute;
                top: 0;
                left: -1em;
                width: 0.8em;     
                font-size: 3em;
                line-height: 1;
                font-weight: bold;
                text-align: right;
                color: #00796B; 
                content: counter(li-counter);
                counter-increment: li-counter;
            }
            </style>
            <?
      }
      else 
         echo json_encode($feedback);
}

// Sanitize names
function clean($string) {
    return strtolower(
        preg_replace(
          array( '#[\\s-]+#', '#[^A-Za-z0-9. _]+#' ),
          array( '_', '' ),
          cleanString(
              trim($string)
          )
        )
    );
}
// Setup form
function f_settings(){
   global $PHP_SELF;
   $currentFolder = dirname(__FILE__).DIRECTORY_SEPARATOR;?>
   <form method="POST" action="<?=$_SELF;?>">
     <ol class="border">
        <li>
            Saisissez vos identifiants TIKO :<br /><br />
             <input type="hidden" name="enr_ok" value="1">
             <label for="email">Email:</label>
             <input type="email" id="email" name="email" required><br><br>
             
             <label for="password">Password:</label>
             <input type="password" id="password" name="password" required><br><br>
            
             <label></label>
             <input type="submit" value="Enregistrer">
             <br />

        </li>
      </ol>
      Les identifiants seront stockés dans le fichier <?=$currentFolder.'<strong>tiko.env</strong>';?>
  </form>
   <style>
       body { font-family:tahoma }
       label { display:inline-block; width:80px; text-align:right }

       ol.border {
          list-style-type: none;
          list-style-type: decimal !ie;     
          margin: 0;
          margin-left: 3em;
          padding: 0;     
          counter-reset: li-counter;
      }

      ol.border > li{
          position: relative;
          margin-bottom: 20px;
          padding-left: 0.5em;
          min-height: 3em;
          border-left: 2px solid #CCCCCC;
      }

      ol.border > li:before {
          position: absolute;
          top: 0;
          left: -1em;
          width: 0.8em;     
          font-size: 3em;
          line-height: 1;
          font-weight: bold;
          text-align: right;
          color: #00796B; 
          content: counter(li-counter);
          counter-increment: li-counter;
      }
      </style> 
  <?php
  exit;
}?>
