<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\JsonResponse;
use Pimple\Psr11\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

final class Services
{
    private const API_NAME = 'Mash Slim4 Api';

    private const API_VERSION = '1.0.0';

    /** @var Container */
    private $container;
    private $pathToUpload="uploadFolder";
    private $imgSmallWidth=600;
    private $imgMediumWidth=1200;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

  
    public function getStatus(Request $request, Response $response): Response
    {
        $this->container->get('db');
        $status = [
            'status' => [
                'database' => 'OK',
            ],
            'api' => self::API_NAME,
            'version' => self::API_VERSION,
            'timestamp' => time(),
        ];

        return JsonResponse::withJson($response, json_encode($status), 200);
    }
    
    
    
    // function sendNotification($args)
    // {
    //     define( 'API_ACCESS_KEY', 'AAAAcIYJN8M:APA91bGjz0eKCJbu-x1OvJ-bqVSDYBWBNZkeeeoDqoduReQ2t2DQHfdGVwan1tDGI-hIyaj5RpKVJ3DFrSLM4SMKSC6ugKrQUmn3MB4Mb5gFbsEo9XUBlUNCc61PV8fgRuq1Dvp1FNYJ' );        
      
    //   $message=$args['message'];
    //     $title=$args['title'];
    //     $topic= $args['topic'];// all / Topic 1 / Topic2 /....
    //     $tokens = $args['notificaionsTokens'];

    //     // if(!isset($topic) || $topic == null ){
    //     //     if($args['multi']!=1)
    //     //         $notificaionsTokens[0]= $tokens ;
    //     //     else
    //     //         $notificaionsTokens= explode(",", $tokens);;
    //     // }
    //     $condition = isset($args['condition'])? $args['condition'] : null;
    //     // echo $condition;
    //       if((!isset($topic) || $topic == null ) && $condition == null){
    //           if($args['multi']!=1)
    //               $notificaionsTokens[0]= $tokens ;
    //           else
    //               $notificaionsTokens= explode(",", $tokens);;
    //       }
  
    //     $privateData=$args['privateData'];
    //     if(!$message) $message="";
    //     if(!$title) $title="";
        
    //     //------------------   

    //   // echo "notificaionsTokens::".print_r($notificaionsTokens);
    //     // prep the bundle
    //     $msg = array
    //         (
    //         'body' => $message,
    //         'title'	=> $title,
    //         'vibrate'	=> 1,
    //         'sound'	=> "sound.mp3",
    //         'largeIcon'	=> 'fcm_push_icon',
    //         //	'smallIcon'	=> 'fcm_push_icon',
    //         'smallIcon' => 'res://notification_icon.png',
    //         'click_action'=>"FLUTTER_NOTIFICATION_CLICK",  //Must be present for Android
    //         'icon'=> 'fcm_push_icon',
    //         'privateData'=> $privateData,
    //         'uniqueId'=>rand(9999,999999),
    //         'android_channel_id' =>'noti_push_app_1'
    //         );
        
    //     $fields = array
    //         (
    //         'data'	=> $msg,
    //         'priority'=>'high',
    //         'content_available'=>true,
    //         'notification'=>array(
    //                             'body' => $message,
    //                             'title'	=> $title,
    //                                 'click_action' => "FLUTTER_NOTIFICATION_CLICK",
    //                                 'sound'=>'sound.caf',
    //                                 'icon'=> 'notification_icon', //'fcm_push_icon',
    //                                 "color"=> "#33C1FF",
    //                                 'android_channel_id' =>'noti_push_app_1'
    //                                             ),

    //             'priority'=> "high",
    //             'apns' => array(
    //               'payload' => array(
    //                   'aps' => array(
    //                       'sound'	=> "sound.caf",
    //                   )
    //             ))             
    //         );
        

            
    //         if(sizeof($notificaionsTokens)>0)
    //         $fields['registration_ids'] = $notificaionsTokens;
    //     else if($condition)
    //       $fields['condition'] = $condition;
    //     else
    //         $fields['to'] =  "/topics/$topic";
            
    //     //print_r($fields); 
    //     //return;  
    //     // data['topics'] = "('part-"+partCategoryId+"' in topics && 'brand-"+brandId+"' in topics)" ;

    
            
    //     //  print_r($fields);   
    //     // return;
    
    //     $headers = array
    //         (
    //         'Authorization: key=' . API_ACCESS_KEY,
    //         'Content-Type: application/json'
    //             );
    
    //     $ch = curl_init();
    //     curl_setopt( $ch,CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send' );      
    //     curl_setopt( $ch,CURLOPT_POST, true );
    //     curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
    //     curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
    //     curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
    //     curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
    //     $result = curl_exec($ch );
    //     curl_close( $ch );
    //     // echo $result;
    //     return $result;
    //     // $this->response=$response;

    //     // $responseData = array();
    //     // $responseData['error'] = $result->error; 
    //     // $responseData['result'] =  $result; 
    //     // return $this->response->withJson($responseData);
            
    // }
    
    
    
        function sendNotification($args)
    {
        // define( 'API_ACCESS_KEY', 'AAAAcIYJN8M:APA91bGjz0eKCJbu-x1OvJ-bqVSDYBWBNZkeeeoDqoduReQ2t2DQHfdGVwan1tDGI-hIyaj5RpKVJ3DFrSLM4SMKSC6ugKrQUmn3MB4Mb5gFbsEo9XUBlUNCc61PV8fgRuq1Dvp1FNYJ' );

        // $message=$args['message'];
        // $title=$args['title'];
        // $topic= $args['topic'];// all / Topic 1 / Topic2 /....
        // $tokens = $args['notificaionsTokens'];

        // $notificationsTokens = [];
        // $conditions = isset($args['condition'])? $args['condition'] : [];

        // if((!isset($topic) || $topic == null ) && $conditions == []){
        //     if($args['multi']!=1){
        //         $notificationsTokens[0]= $tokens;
        //     }
        //     else{
        //         $notificationsTokens= explode(",", $tokens);;
        //     }
        // }

        // $privateData=$args['privateData'];
        // if(!$message) $message="";
        // if(!$title) $title="";

        // //------------------

        // // prep the bundle
        // $msg = array
        // (
        //     'body' => $message,
        //     'title'	=> $title,
        //     'vibrate'	=> 1,
        //     'sound'	=> "sound.mp3",
        //     'largeIcon'	=> 'fcm_push_icon',
        //     'smallIcon' => 'res://notification_icon.png',
        //     'click_action'=>"FLUTTER_NOTIFICATION_CLICK",  //Must be present for Android
        //     'icon'=> 'fcm_push_icon',
        //     'privateData'=> $privateData,
        //     'uniqueId'=>rand(9999,999999),
        //     'android_channel_id' =>'noti_push_app_1'
        // );

        // $fields = array
        // (
        //     'data'	=> $msg,
        //     'priority'=>'high',
        //     'content_available'=>true,
        //     'notification'=>array(
        //         'body' => $message,
        //         'title'	=> $title,
        //         'click_action' => "FLUTTER_NOTIFICATION_CLICK",
        //         'sound'=>'sound.caf',
        //         'icon'=> 'notification_icon', //'fcm_push_icon',
        //         "color"=> "#33C1FF",
        //         'android_channel_id' =>'noti_push_app_1'
        //     ),
        //     'priority'=> "high",
        //     'apns' => array(
        //         'payload' => array(
        //             'aps' => array(
        //                 'sound'	=> "sound.caf",
        //             )
        //         ))
        // );

        // $headers = array
        // (
        //     'Authorization: key=' . API_ACCESS_KEY,
        //     'Content-Type: application/json'
        // );

        // $ch = curl_init();
        // curl_setopt( $ch,CURLOPT_URL, 'https://fcm.googleapis.com/fcm/send' );
        // curl_setopt( $ch,CURLOPT_POST, true );
        // curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
        // curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
        // curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );


        // if(sizeof($notificationsTokens)>0){
        //     $fields['registration_ids'] = $notificationsTokens;
        // }
        // else if($conditions){
        //     $fields['condition'] = $conditions;
        // }
        // else{
        //     $fields['to'] =  "/topics/$topic";
        // }

        // if (isset($fields['condition'])){
        //     foreach ($fields['condition'] as $condition){
        //         $fields['condition'] = $condition;
        //         curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ));
        //         $result = curl_exec($ch);
        //     }
        // }else{
        //     curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ));
        //     $result = curl_exec($ch);
        // }

        // curl_close( $ch );
        // return $result;

var_dump('a");
return;

// Initialize Firebase Admin SDK
$factory = (new Factory)->withServiceAccount('/zefaaf-16c8a-2ba0f53606d6.json');
$messaging = $factory->createMessaging();

$message = $args['message'] ?? '';
$title = $args['title'] ?? '';
$topic = $args['topic'] ?? null;
$tokens = $args['notificaionsTokens'] ?? [];
$privateData = $args['privateData'] ?? '';
$conditions = $args['condition'] ?? [];

// Prepare the message
$notification = Notification::create($title, $message);
$data = [
    'privateData' => $privateData,
    'uniqueId' => rand(9999, 999999),
];

// Define Android-specific settings
$androidConfig = [
    'priority' => 'high',
    'notification' => [
        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        'icon' => 'fcm_push_icon',
        'color' => '#33C1FF',
        'android_channel_id' => 'noti_push_app_1',
    ],
];

// Define iOS-specific settings
$apnsConfig = [
    'headers' => [
        'apns-priority' => '10',
    ],
    'payload' => [
        'aps' => [
            'sound' => 'default',
        ],
    ],
];

// Send to tokens
if (!empty($tokens)) {
    if ($args['multi'] != 1) {
        $tokens = [$tokens]; // Ensure tokens are in an array
    }

    $message = CloudMessage::new()
        ->withNotification($notification)
        ->withData($data)
        ->withAndroidConfig($androidConfig)
        ->withApnsConfig($apnsConfig)
        ->withTarget('token', $tokens);

    $result = $messaging->sendMulticast($message, $tokens);
}
// Send to topic
elseif (!empty($topic)) {
    $message = CloudMessage::new()
        ->withNotification($notification)$apnsConfig
        ->withData($data)
        ->withAndroidConfig($androidConfig)
        ->withApnsConfig($apnsConfig)
        ->withTarget('topic', $topic);

    $result = $messaging->send($message);
}
// Send to conditions
elseif (!empty($conditions)) {
    foreach ($conditions as $condition) {
        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withData($data)
            ->withAndroidConfig($androidConfig)
            ->withApnsConfig($apnsConfig)
            ->withCondition($condition);

        $result = $messaging->send($message);
    }
}

return $result;





    }

    //------
    public function upload(Request $request, Response  $response,$args,$internal = false) 
    {
            
        $inputs = $request->getParsedBody();
        $folderName = $args['folderName'];

        $pathToUpload=$this->pathToUpload;
        $thumbSmallWidth=$this->imgSmallWidth;
        $thumbMediumWidth=$this->imgMediumWidth;
        
        $uploadedFiles = $request->getUploadedFiles();
        if (empty($uploadedFiles['attachment'])) {
                echo "no files";
            }
            
        $file = $uploadedFiles['attachment'];
        $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
        $extension = strtolower($extension);
        
        $thumbExtensions = array('jpeg','jpg','Jpg','png','Png','JPG');
        if(in_array($extension,$thumbExtensions)) {
            $requestSmallThumb=true ;
            $requestMediumThumb=true ;
        }
        else{
            $requestSmallThumb=false ;
            $requestMediumThumb=false ;
        }
        
        list($usec, $sec) = explode(" ", microtime());
        $basename = rand(10,99).substr($usec,2).rand(100,999);
        $filename = $basename.".".$extension;
       // $folderName='';
        

        $src = isset($folderName)? "$folderName/$filename" : "$pathToUpload/original/$filename";
        $file->moveTo($src);

        if($requestSmallThumb || $requestMediumThumb){
            $thumbSmallDest="$pathToUpload/small/$filename";
            $this->createThumb($src,$thumbSmallDest,$thumbSmallWidth,false);    
        
            $thumbMediumDest="$pathToUpload/medium/$filename";
            $this->createThumb($src,$thumbMediumDest,$thumbMediumWidth,false);

            unlink($src); // delete the original file if not requested to kkep it
    
        }
        

        $responseData = array();
        $responseData['error'] = false; 

        $responseData['fileName']=  $filename;
        $responseData['filePath']= isset($folderName)? "/$folderName/" : $pathToUpload;
        
        return $responseData;
    
    }
    public function createThumb($src,$dest,$desired_width , $desired_height)
    {
        if (!$desired_height&&!$desired_width) return false;
        $fparts = pathinfo($src);
        $ext = strtolower($fparts['extension']);
        if (!in_array($ext,array('gif','jpg','png','jpeg'))) return false;

        if ($ext == 'gif')
            $resource = imagecreatefromgif($src);
        else if ($ext == 'png')
            $resource = imagecreatefrompng($src);
        else if ($ext == 'jpg' || $ext == 'jpeg')
            $resource = imagecreatefromjpeg($src);
        
        $width  = imagesx($resource);
        $height = imagesy($resource);
        //if the requested new dimentiones is bigger than the original keep the original .
        if(($desired_width && $desired_width>$width) ||($desired_height && $desired_height>$height)) 
        {
            $desired_width=$width;
            $desired_height=$height;
        }

        // find the "desired height" or "desired width" of this thumbnail, relative to each other, if one of them is not given  
        if(!$desired_height) $desired_height = floor($height*($desired_width/$width));
        if(!$desired_width)  $desired_width  = floor($width*($desired_height/$height));
    
        // create a new, "virtual" image 
        $virtual_image = imagecreatetruecolor(intval($desired_width),intval($desired_height));
        //-----Fix png transperent---
        if ($ext == 'png'){
          // integer representation of the color black (rgb: 0,0,0)
          $background = imagecolorallocate($virtual_image , 0, 0, 0);
          // removing the black from the placeholder
          imagecolortransparent($virtual_image, $background);
  
          // turning off alpha blending (to ensure alpha channel information
          // is preserved, rather than removed (blending with the rest of the
          // image in the form of black))
          imagealphablending($virtual_image, false);
  
          // turning on alpha channel information saving (to ensure the full range
          // of transparency is preserved)
          imagesavealpha($virtual_image, true);
        }
        
       
          //-------


        // copy source image at a resized size 
        imagecopyresized($virtual_image,$resource,0,0,0,0,intval($desired_width),intval($desired_height),intval($width),intval($height));
        
        // create the physical thumbnail image to its destination 
        // Use correct function based on the desired image type from $dest thumbnail source 
        $fparts = pathinfo($dest);
        $ext = strtolower($fparts['extension']);
        // if dest is not an image type, default to jpg 
        if (!in_array($ext,array('gif','jpg','png','jpeg'))) $ext = 'jpg';
        $dest = $fparts['dirname'].'/'.$fparts['filename'].'.'.$ext;
        
        if ($ext == 'gif')
            imagegif($virtual_image,$dest);
        else if ($ext == 'png')
            imagepng($virtual_image,$dest,1);
        else if ($ext == 'jpg' || $ext == 'jpeg')
            imagejpeg($virtual_image,$dest,100);
        
    
    }
    public function sendEmail($email,$title,$message) 
    {
            
      

        try {
            $mail = new PHPMailer(true);

           // $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      // Enable verbose debug output
            $mail->isSMTP();                                            // Send using SMTP
            $mail->Host       = 'zefaaf.net';                    // Set the SMTP server to send through
            $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
            $mail->Username   = 'noreply@zefaaf.net';                     // SMTP username
            $mail->Password   = '2+uU_5blc6#6';                               // SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
            $mail->Port       = 587;                                    // TCP port 587 to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
            $mail->CharSet = 'UTF-8';

            //Recipients
            $mail->setFrom('noreply@zefaaf.net', 'Zefaaf.net');
            $mail->addAddress($email, '');     // Add a recipient
            $mail->addReplyTo('noreply@zefaaf.net', 'Information');
            // $mail->addCC('cc@example.com');
            // $mail->addBCC('bcc@example.com');
        
            // Attachments
            // $mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
            // $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
        
            $htmlMessage = '<!doctype html>
            <html>
              <head>
                <meta name="viewport" content="width=device-width">
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                <title>Simple Transactional Email</title>
                <style>
               
                @media only screen and (max-width: 620px) {
                  table[class=body] h1 {
                    font-size: 28px !important;
                    margin-bottom: 10px !important;
                  }
                  table[class=body] p,
                        table[class=body] ul,
                        table[class=body] ol,
                        table[class=body] td,
                        table[class=body] span,
                        table[class=body] a {
                    font-size: 16px !important;
                  }
                  table[class=body] .wrapper,
                        table[class=body] .article {
                    padding: 10px !important;
                  }
                  table[class=body] .content {
                    padding: 0 !important;
                  }
                  table[class=body] .container {
                    padding: 0 !important;
                    width: 100% !important;
                  }
                  table[class=body] .main {
                    border-left-width: 0 !important;
                    border-radius: 0 !important;
                    border-right-width: 0 !important;
                  }
                  table[class=body] .btn table {
                    width: 100% !important;
                  }
                  table[class=body] .btn a {
                    width: 100% !important;
                  }
                  table[class=body] .img-responsive {
                    height: auto !important;
                    max-width: 100% !important;
                    width: auto !important;
                  }
                }
            
            
                @media all {
                  .ExternalClass {
                    width: 100%;
                  }
                  .ExternalClass,
                        .ExternalClass p,
                        .ExternalClass span,
                        .ExternalClass font,
                        .ExternalClass td,
                        .ExternalClass div {
                    line-height: 100%;
                  }
                  .apple-link a {
                    color: inherit !important;
                    font-family: inherit !important;
                    font-size: inherit !important;
                    font-weight: inherit !important;
                    line-height: inherit !important;
                    text-decoration: none !important;
                  }
                  #MessageViewBody a {
                    color: inherit;
                    text-decoration: none;
                    font-size: inherit;
                    font-family: inherit;
                    font-weight: inherit;
                    line-height: inherit;
                  }
                  .btn-primary table td:hover {
                    background-color: #34495e !important;
                  }
                  .btn-primary a:hover {
                    background-color: #34495e !important;
                    border-color: #34495e !important;
                  }
                }
                </style>
              </head>
              <body dir="rtl" class="" style="background-color: #f6f6f6; font-family: sans-serif; -webkit-font-smoothing: antialiased; font-size: 14px; line-height: 1.4; margin: 0; padding: 0; -ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%;">
                <span class="preheader" style="align-items: center; align-content: center;">
                </span>
                
                
                <table border="0" cellpadding="0" cellspacing="0" class="body" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%; background-color: #f6f6f6;">
                  <tr>
                    <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;">&nbsp;</td>
            
                    <td  class="container" style="font-family: sans-serif; font-size: 14px; vertical-align: top; display: block; Margin: 0 auto; max-width: 580px; padding: 10px; width: 580px;">
            
                    <img  src="https://zefaaf.net/email/zefaaf.net.png" alt="Logo Placeholder" title="Logo Placeholder" style="text-decoration: none; -ms-interpolation-mode: bicubic; height: auto; border: 0;  " width="136">
            
                  </td>
                  <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;">&nbsp;</td>
            
                </tr>
                  <tr>
                    <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;">&nbsp;</td>
                    <td class="container" style="font-family: sans-serif; font-size: 14px; vertical-align: top; display: block; Margin: 0 auto; max-width: 580px; padding: 10px; width: 580px;">
                      <div class="content" style="box-sizing: border-box; display: block; Margin: 0 auto; max-width: 580px; padding: 10px;">
            
                        <!-- START CENTERED WHITE CONTAINER -->
                        <table class="main" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%; background: #ffffff; border-radius: 3px;">
            
                          <!-- START MAIN CONTENT AREA -->
                          <tr>
                            <td class="wrapper" style="font-family: sans-serif; font-size: 14px; vertical-align: top; box-sizing: border-box; padding: 20px;">
                              <table border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%;">
                                <tr>
                                  <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;">
                                    <p style="font-family: sans-serif; font-size: 14px; font-weight: normal; margin: 0; Margin-bottom: 15px;">'.$message.'</p>
                                   </td>
                                </tr>
                              </table>
                            </td>
                          </tr>
            
                        <!-- END MAIN CONTENT AREA -->
                        </table>
            
                        <!-- START FOOTER -->
                        <div class="footer" style="clear: both; Margin-top: 10px; text-align: center; width: 100%;">
                          <table border="0" cellpadding="0" cellspacing="0" style="border-collapse: separate; mso-table-lspace: 0pt; mso-table-rspace: 0pt; width: 100%;">
                           
                            <tr >
                              <td colspan="2" class="content-block powered-by" style="font-family: sans-serif; vertical-align: top; padding-bottom: 10px; padding-top: 10px; font-size: 12px; color: #999999; text-align: center;">
                                Powered by <a href="https:zefaaf.net" style="color: #999999; font-size: 12px; text-align: center; text-decoration: none;">Zefaaf.net</a>.
                              </td>
                             
                            </tr>
                            <tr >
                              <td colspan="2" class="content-block" style="font-family: sans-serif; vertical-align: top; padding-bottom: 10px; padding-top: 10px; font-size: 12px; color: #999999; text-align: center;">
                                <a href="https://www.facebook.com/zefaaf.net" target="_blank">
                                  <img width="32" height="32" src="https://d2fi4ri5dhpqd1.cloudfront.net/public/resources/social-networks-icon-sets/t-outline-circle-dark-gray/facebook@2x.png" alt="Facebook" title="facebook" style="text-decoration: none; -ms-interpolation-mode: bicubic; height: auto; border: 0;"></a>
                                  <a href="https://www.instagram.com/zefaaf_net" target="_blank"><img width="32" height="32" src="https://d2fi4ri5dhpqd1.cloudfront.net/public/resources/social-networks-icon-sets/t-outline-circle-dark-gray/instagram@2x.png" alt="Instagram" title="instagram" style="text-decoration: none; -ms-interpolation-mode: bicubic; height: auto; border: 0; display: "></a>
            
                                </td>
                                <td>
            
            
                                </td>
            
                            </tr>
                          </table>
                        </div>
                        <!-- END FOOTER -->
            
                      <!-- END CENTERED WHITE CONTAINER -->
                      </div>
                    </td>
                    <td style="font-family: sans-serif; font-size: 14px; vertical-align: top;">&nbsp;</td>
                  </tr>
                </table>
              </body>
            </html>';
            



            // Content
            $mail->isHTML(true);                                  // Set email format to HTML
            $mail->Subject = $title;
            $mail->Body    = $htmlMessage;

            
          //  $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
        
            $mail->send();
           // echo 'Message has been sent';
        } catch (Exception $e) {
           // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }  
        

      
    
    }

}
