<?php

declare(strict_types=1);

namespace App\Controller;

use App\Helper\JsonResponse;
use Pimple\Psr11\Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use \Firebase\JWT\JWT;
use \DateTime;
use \Tuupola\Base62;


final class Mobile
{
    private const API_NAME = 'Mash-APi';

    private const API_VERSION = '1.0.0';

    /** @var Container */
    private $container;

    private $db;
    private $pageSize = 40;

    private $privateKey = <<<EOD
    -----BEGIN RSA PRIVATE KEY-----
    MIICWwIBAAKBgQCZsw83Sg+Y+1cuMJlz9qQyB8WKy+SxFVFTWic9a5jXLpAPYfvf
    DK+UiaveosXvFp/UepmGCTP44H4y/qEI/7EdBFfZKO8e39CqlbHalthBTZDniSpC
    rSkjXvG1vmeE20OnuSTOkS+ZGzU5ub2zZPteg1ZnLYNQw9gp6+faklJY5wIDAQAB
    AoGAIK0Vxg5jxIVi4noIlcPQ4qYOVFTGuPMsSAk5oHA4nT7T98FAGAqNZYnjVUDL
    zenEbxrler6IIxgvHE5QFCawE4NaH/3IAK7i76TX9uRzVaZmmUsMedazaRqekF/U
    zXIfxDmQn945Cb6XdvsjcoTuKNKEFD3K8txsIyqwn+jOGKECQQDdJy6Fk/mPv8Uy
    SoSVKN/q1Vlo6kZnQmSGZ9C5lUB/AC4tJs0v6MVIhJZVakjjdQsDfI356gTK8auf
    IluLjF0XAkEAserzXNtBs7tNRuUbwScVNqGeAd06qq0cZ2ZcLttkxdpfAlul3Rkw
    Gi2Rij29zhyp9BDrMRQAmMT8xNQluP9ksQJAPNhd3VSEEf+yEo8cASnYyXRfgBUM
    v7YtkCkZ7SVaNFbpXXTSeT7yoGwSLHfsi+AU4qWvLjYrkWaCUGsUgsVgiwJAT9+4
    7eEJOBVIdiF5Ole/cE1SUGfvZJxe+hS8IdUhAqosqTRX3FDohXgbtMJPKe84ZXgK
    /neKZQtap0rOvKT7oQJAFSbVG+nCPpnSLT6a46iQM0k28K9/SY1MOJ3q5EJKdT+B
    xc6WmSQE+tLRoF7jIlE1bQEeS78xBe2qiR2y3wNuzQ==
    -----END RSA PRIVATE KEY-----
    EOD;

    private $publicKey = <<<EOD
    -----BEGIN PUBLIC KEY-----
    MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCZsw83Sg+Y+1cuMJlz9qQyB8WK
    y+SxFVFTWic9a5jXLpAPYfvfDK+UiaveosXvFp/UepmGCTP44H4y/qEI/7EdBFfZ
    KO8e39CqlbHalthBTZDniSpCrSkjXvG1vmeE20OnuSTOkS+ZGzU5ub2zZPteg1Zn
    LYNQw9gp6+faklJY5wIDAQAB
    -----END PUBLIC KEY-----
    EOD;
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get('db');
    }
    private function generateToken(int $userId, int $userGender, string $deviceToken, string $userName)
    {
        $now = new DateTime("Now");
        $future = new DateTime("now +30 days");
        $payload = [
            //  "jti" => '20',
            "iat" => $now->getTimeStamp(),
            "exp" => $future->getTimeStamp(),
            "userId" => "$userId",
            "userGender" => "$userGender",
            "userDeviceToken" => "$deviceToken",
            "userName" => "$userName"
        ];

        //  $token = JWT::encode($payload, 'Secnkj9ne8nkmndsa8enk3usnfw0nlsAS', "HS256");
        $token = JWT::encode($payload, $this->privateKey, "RS256");

        return $token;
    }

    public function getStatus(Request $request, Response $response, $args)
    {
        $this->container->get('db');
        $userId = $request->getAttribute("userId");

        $status = [
            'status' => 'success',
            'userId' => $userId,
            'version' => self::API_VERSION,
            'timestamp' => time(),
        ];

        return JsonResponse::withJson($response, json_encode($status), 200);
    }

    public function getAppSettings(Request $request, Response $response, $args, $internal = 0)
    {
        $sql = "select * from settings";
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['data'] = $data;

            $sql = "select sum(if(gender=1,1,0)) as female,sum(if(gender=0,1,0)) as male from users";

            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();
            $responseData['footerStatistic'] = $data[0];

            $sql = "select id,type,gender,title from fixedData where active=1 order by type,id";

            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();
            $responseData['fixedData'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function detectedCountry($detectedCountry)
    {
        $sql = "select nameAr from countries where LOWER(nameEn) =LOWER('$detectedCountry') 
        or LOWER(nameAr)=LOWER('$detectedCountry') or LOWER(shortcut) =LOWER('$detectedCountry')
        or LOWER(nameArShort) =LOWER('$detectedCountry')";

        $sth = $this->db->prepare($sql);
        $sth->execute();
        $data = $sth->fetchAll();
        $detectedCountry = $data[0]['nameAr'] ? $data[0]['nameAr'] : $detectedCountry;
        return $detectedCountry;
    }

    public function login(Request $request, Response $response, $args, $internal = 0)
    {

        $responseData = array();

        $inputs = array();
        $inputs = $request->getParsedBody();
        $mobile = $inputs['mobile'];
        $password =  md5($inputs['password']);
        $deviceToken = $inputs['deviceToken'];
        $detectedCountry = $inputs['detectedCountry'];
        //$services = new Services($this->container);
        //$services->sendEmail('mash@dreamsoft-it.com','Zefaaf Detected country',$inputs['detectedCountry']."::".$mobile);

        $loginWay = $inputs['loginWay'];
        $tokenField = ($loginWay == 'web') ? 'webToken' : 'deviceToken';
        $notificationOpenDate = $inputs['notificationOpenDate'];

        $sql = array();

        $sql[0] = " select users.*,packageLevel,countries1.nameAr as residentCountryName,nationalityCountryId,countries2.nameAr as 
                    nationalityCountryName,cityId,cities.nameAr as cityName,residentCountryId
                    from users inner join countries as countries1 inner join countries as countries2
                    inner join cities inner join packages
                    on users.residentCountryId = countries1.id and users.nationalityCountryId = countries2.id
                    and users.cityId=cities.id  and users.packageId = packages.id
                    where (mobile='$mobile' or userName='$mobile') and password='$password' 
                    and users.active=1 and (susbendedTillDate < CURDATE() or ISNull(susbendedTillDate))";

        $sql[1] = "update users set $tokenField='$deviceToken' ,lastAccess=NOW(),
                    detectedCountry=:detectedCountry ,
                    packageId = if(packageRenewDate<Now(),0,packageId),
                    available =1 ,
                    packageRenewDate = if(packageRenewDate<Now(),NOW() + INTERVAL 10 Year,packageRenewDate) 
                    where id=:userId";
        $responseData = array();
        try {
            $sth = $this->db->prepare($sql[0]);
            $sth->execute();
            $data = $sth->fetchAll();

            if (sizeof($data) > 0) //user exists
            {
                $responseData['status'] = 'success';
                $responseData['token'] = $this->generateToken($data[0]['id'], $data[0]['gender'], $deviceToken, $data[0]['userName']);
                $responseData['pathToUpload'] = $pathToUpload;
                $responseData['data'] = $data;
                $responseData['data'][0]['available'] = 1;
                $userId = $data[0]['id'];
                //---- call myUpdates --
                $request = $request->withAttribute("userId", $data[0]['id']);
                $args['notificationOpenDate'] = $notificationOpenDate;

                $updates = $this->getMyUpdates($request, $response, $args, 1);
                $responseData['updates'] = $updates['data'][0];
                //----get latest users---
                $args['pageSize'] = 100;
                $args['userGender'] = $data[0]['gender'];
                $args['orderBy'] = 'lastAccess';
                $args['residentCountryId'] = $data[0]['residentCountryId'];


                $latestUsers = $this->search($request, $response, $args, 1);
                $responseData['latestUsers'] = $latestUsers['data'];

                //---Update user data --
                $detectedCountry = $this->detectedCountry($detectedCountry);

                $sth = $this->db->prepare($sql[1]);
                $sth->bindParam(':userId', $userId);
                $sth->bindParam(':detectedCountry', $detectedCountry);

                $sth->execute();
            } else {
                $responseData['status'] = 'error';
                $responseData['rowsCount'] = 0;
                $responseData['errorMessage'] = 'User not exists or token changed ,please signout if you already signed in';
            }
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function loginByToken(Request $request, Response $response, $args, $internal = 0)
    {

        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();
        $sql = array();

        $userId = $request->getAttribute("userId");
        $userGender = $request->getAttribute("userGender");
        $userDeviceToken = $request->getAttribute("userDeviceToken");

        $deviceToken = $inputs['deviceToken'];
        $detectedCountry = $inputs['detectedCountry'];

        $notificationOpenDate = $inputs['notificationOpenDate'];
        $loginWay = $inputs['loginWay'];

        $tokenField = ($loginWay == 'web') ? 'webToken' : 'deviceToken';

        $mobileType = ($inputs['mobileType'] > 0) ? $inputs['mobileType'] : 0;

        $sql[0] = " select users.*,packageLevel,countries1.nameAr as residentCountryName,nationalityCountryId,countries2.nameAr as 
                    nationalityCountryName,cityId,cities.nameAr as cityName,if(available=0,1,available) as available,residentCountryId
                    from users inner join countries as countries1 inner join countries as countries2
                    inner join cities   inner join packages 

                    on users.residentCountryId = countries1.id and users.nationalityCountryId = countries2.id
                    and users.cityId=cities.id and users.packageId = packages.id
                      where users.id=$userId and users.active=1
                    and (susbendedTillDate < CURDATE() or ISNull(susbendedTillDate))";
        // echo $sql[0];
        $sql[1] = "update users set ";
        $sql[1] .= ($deviceToken != null) ? "$tokenField='$deviceToken', " : "";
        $sql[1] .= " lastAccess=CURRENT_TIMESTAMP(),mobileType=$mobileType ,";
        if ($detectedCountry) $sql[1] .= "detectedCountry=:detectedCountry ,";
        $sql[1] .= " packageId = if(packageRenewDate<Now(),0,packageId),
                    packageRenewDate = if(packageRenewDate<Now(),NOW() + INTERVAL 10 Year,packageRenewDate) 
                    where id=:userId";

        $responseData = array();
        try {
            $sth = $this->db->prepare($sql[0]);
            $sth->execute();
            $data = $sth->fetchAll();

            if (sizeof($data) > 0) //user exists
            {
                $responseData['status'] = 'success';
                $responseData['token'] = $this->generateToken($data[0]['id'], $data[0]['gender'], $deviceToken, $data[0]['userName']);
                $responseData['pathToUpload'] = $pathToUpload;
                $responseData['data'] = $data;
                // $responseData['data'][0]['available']=1;
                $userId = $data[0]['id'];
                //---- call myUpdates --
                $request = $request->withAttribute("userId", $userId);
                $args['notificationOpenDate'] = $notificationOpenDate;

                $updates = $this->getMyUpdates($request, $response, $args, 1);
                $responseData['updates'] = $updates['data'][0];
                //----get latest users---
                $args['pageSize'] = 100;
                $args['userGender'] = $data[0]['gender'];
                $args['orderBy'] = 'lastAccess';
                $args['residentCountryId'] = $data[0]['residentCountryId'];

                $latestUsers = $this->search($request, $response, $args, 1);
                $responseData['latestUsers'] = $latestUsers['data'];

                //---Update user data --
                $detectedCountry = $this->detectedCountry($detectedCountry);

                $sth = $this->db->prepare($sql[1]);
                $sth->bindParam(':userId', $userId);
                $sth->bindParam(':detectedCountry', $detectedCountry);

                $sth->execute();
            } else {
                $responseData['status'] = 'error';
                $responseData['rowsCount'] = 0;
                $responseData['errorMessage'] = 'User not exists or token changed ,please signout if you already signed in';
            }
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function getWebHome(Request $request, Response $response, $args, $internal = 0)
    {
        $gender = $args['gender'];
        $responseData = array();
        try {
            $responseData['status'] = 'success';

            $sql = "select websiteHomeUsers,websiteSuccessStories,displayLiveUsers from settings ";

            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $websiteHomeUsers = $data[0]['websiteHomeUsers'];
            $websiteSuccessStories = $data[0]['websiteSuccessStories'];
            //----get latest users---
            $args['pageSize'] = 100; //$websiteHomeUsers;
            $args['userGender'] = isset($gender) ? $gender : -1;
            $args['orderBy'] = 'lastAccess';
            if ($gender >= 0)
                $args['residentCountryId'] = $args['residentCountryId'];

            $latestUsers = $this->search($request, $response, $args, 1);
            $responseData['latestUsers'] = $latestUsers['data'];

            $args['pageSize'] = $websiteSuccessStories;
            $successStories = $this->getSuccessStories($request, $response, $args, 1);
            $responseData['successStories'] = $successStories['data'];

            //----get available users---

            $sql = "select sum(if(available=1 and gender=0,1,0)) as availableMens ,
                    sum(if(available=1 and gender=1,1,0)) as availableWomens from users ";

            $sth = $this->db->prepare($sql);
            $sth->execute();
            $availableUsers = $sth->fetchAll();

            $responseData['displayLiveUsers'] =  $data[0]['displayLiveUsers'];

            $responseData['websiteAvailableUsers'] =  $availableUsers[0];
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function getCountries(Request $request, Response $response, $args, $internal = 0)
    {

        $pageSize = $this->pageSize;
        $pagReg = $args['page'] ?? null;
        $page = $pagReg * $pageSize;

        $sql = " select id,shortcut,nameEn,nameAr,phoneCode,timezone,currencyEn,currencyAr 
                from countries where active=1 order by nameAr asc  ";
        if (isset($args['page'])) $sql .= " limit $page,$pageSize ";
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function getCities(Request $request, Response $response, $args, $internal = 0)
    {
        $pageSize = $this->pageSize;
        $countryId = $args['countryId'];
        $page = $args['page'] * $pageSize;

        $sql = " select * from  cities where countryId=$countryId order by nameAr asc ";
        if (isset($args['page'])) $sql .= " limit $page,$pageSize ";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function checkMobile(Request $request, Response $response, $args, $internal = 0)
    {

        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();
        $mobile = ltrim($args['mobile'], '0');
        // $userId = $inputs['userId'];

        $sql = " select mobile from users where ( mobile='$mobile' or CONCAT(mobileCode,mobile) ='$mobile')";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();
            //---       
            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            //  $responseData['data'] = $data; 
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function checkUserName(Request $request, Response $response, $args, $internal = 0)
    {

        $responseData = array();
        $userName = trim($args['userName']);
        $userName = preg_replace('/\s+/', ' ', $userName);
        $userName = preg_replace('/\t+/', ' ', $userName);
        $userName = preg_replace('/\n\r+/', ' ', $userName);



        $userLength = mb_strlen($userName);
        $sql = " select userName from users where userName='$userName' ";

        $responseData = array();

        try {
            $responseData['status'] = 'success';
            $responseData['len'] = mb_strlen($userName);
            $responseData['userName'] = $userName;


            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();
            $responseData['rowsCount'] = count($data);

            //---    
            $args['message'] = $userName;
            $spam = $this->checkForSpamChat($request,  $response, $args, 1, 4);
            //print_r($spam);
            if (($spam['status'] == 'error') || $userLength < 8 || $userLength > 12)
                $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function register(Request $request, Response $response, $args, $internal = 0)
    {

        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();
        $mobileCode = $inputs['mobileCode'];
        $mobile = ltrim($inputs['mobile'], '0');
        $name = $inputs['name'];
        $userName = trim($inputs['userName']);
        $email = isset($inputs['email']) ? trim($inputs['email']) : '';
        $gender = $inputs['gender'];
        $password =  md5($inputs['password']);

        $residentCountryId = $inputs['residentCountryId'];
        $nationalityCountryId = $inputs['nationalityCountryId'];
        $cityId = $inputs['cityId'];
        $mariageStatues = $inputs['mariageStatues'];
        $mariageKind = isset($inputs['mariageKind']) ? $inputs['mariageKind'] : 0;
        $age = $inputs['age'];
        $kids = isset($inputs['kids']) ? $inputs['kids'] : 0;
        $weight = $inputs['weight'];
        $height = $inputs['height'];
        $smoking = $inputs['smoking'];
        $religiosity = isset($inputs['religiosity']) ? $inputs['religiosity'] : 0;
        $skinColor = $inputs['skinColor'];
        $veil = isset($inputs['veil']) ? $inputs['veil'] : 0;


        $prayer = $inputs['prayer'];
        $education = $inputs['education'];
        $financial = $inputs['financial'];
        $workField = $inputs['workField'];
        $job = isset($inputs['job']) ? $inputs['job'] : '';
        $income = $inputs['income'];
        $helath = $inputs['helath'];
        $aboutMe = $inputs['aboutMe'];
        $aboutOther = $inputs['aboutOther'];
        $detectedCountry = $inputs['detectedCountry'];
        $deviceToken = $inputs['deviceToken'];

        $telesalesCode = isset($inputs['telesalesCode']) ? $inputs['telesalesCode'] : '';

        $args['userName'] = $userName;
        $checkUserName = $this->checkUserName($request,  $response, $args, 1);
        if ($checkUserName['rowsCount'] == 1) //not allowed 
        {
            $responseData['status'] = 'error';
            $responseData['rowsCount'] = 0;
            $responseData['errorCode'] = '6';
            $responseData['errorMessage'] = 'not allowed username';
            return JsonResponse::withJson($response, json_encode($responseData), 200);
        }


        //--- check mobile and check user name --
        $sql = " select mobile,userName from users where (mobile='$mobile' or userName='$userName') ";
        $sth = $this->db->prepare($sql);
        $sth->execute();
        $data = $sth->fetchAll();
        if (count($data) > 0) { //Duplicate username or mobile
            $responseData['status'] = 'error';
            $responseData['rowsCount'] = 0;
            $responseData['errorCode'] = '1';
            $responseData['errorMessage'] = 'Duplicate userName or mobile';
        } else {

            $args['message'] = $aboutMe;
            // $spam1 = $this->checkForSpamChat($request,  $response, $args, 1);
            $args['message'] = $aboutOther;
            // $spam2 = $this->checkForSpamChat($request,  $response, $args, 1);
            // if ($spam1['status'] == 'error' || $spam2['status'] == 'error') {
            //     $responseData['status'] = 'error';
            //     $responseData['rowsCount'] = 0;
            //     $responseData['errorCode'] = ($spam1['status'] == 'error') ? '2' : '4';
            //     $responseData['errorMessage'] = 'Spam in aboutMe or aoutOther';
            //     return JsonResponse::withJson($response, json_encode($responseData), 200);
            // }
            if ($job != '') {
                $args['message'] = $job;
                // $spam = $this->checkForSpamChat($request,  $response, $args, 1);
                // if ($spam['status'] == 'error') {
                //     $job = ' ';
                //     $responseData['status'] = 'error';
                //     $responseData['rowsCount'] = 0;
                //     $responseData['errorCode'] = '5';
                //     $responseData['errorMessage'] = 'Spam in job';
                //     return JsonResponse::withJson($response, json_encode($responseData), 200);
                // }
            }


            $sql = "select approveNewUsers from settings ";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();
            $active = $data[0]['approveNewUsers'];
            $telesalesId = 0;
            //--select telesales id af iany
            if ($telesalesCode != '') {
                $sql = "select id from telesales where code='$telesalesCode' ";
                $sth = $this->db->prepare($sql);
                $sth->execute();
                $telesales = $sth->fetchAll();
                if (count($telesales) > 0)
                    $telesalesId = $telesales[0]['id'];
                else {
                    $responseData['status'] = 'error';
                    $responseData['rowsCount'] = 0;
                    $responseData['errorCode'] = '17';
                    $responseData['errorMessage'] = 'telesales not exists';
                    return JsonResponse::withJson($response, json_encode($responseData), 200);
                }
            }


            $sql = " insert into users
            (mobileCode,mobile,name,userName,email,gender,password,residentCountryId,nationalityCountryId,cityId,
            mariageStatues,mariageKind,age,kids,weight,height,skinColor,smoking,religiosity,prayer,education,financial,
            workField,job,income,helath,aboutMe,aboutOther,detectedCountry,deviceToken,active,veil,telesalesId,packageId,packageRenewDate)
            values('$mobileCode','$mobile','$name','$userName','$email','$gender','$password','$residentCountryId','$nationalityCountryId','$cityId',
            '$mariageStatues','$mariageKind','$age','$kids','$weight','$height','$skinColor','$smoking','$religiosity','$prayer','$education',
            '$financial','$workField','$job','$income','$helath','$aboutMe','$aboutOther','$detectedCountry','$deviceToken',$active,$veil,'$telesalesId',";

            $sql .= ($gender == '1' || $gender == 1) ? "11, NOW() + INTERVAL 10 year)" : "0, NOW() + INTERVAL 10 year) "; //Payed
            // $sql.="11, NOW() + INTERVAL 10 year)" ; // Free INTERVAL 10 day //payed

            $responseData = array();

            try {
                $sth = $this->db->prepare($sql);
                $sth->execute();
                $lastInsertId = $this->db->lastInsertId();

                //---       
                $responseData['status'] = 'success';
                $responseData['rowsCount'] = 1;
                // $responseData['id'] = $lastInsertId; 
                //Send welcome Notification ---
                if ($gender == '1' || $gender == 1) {
                    $title = 'زفاف ترحب بك';
                    $message = 'لمزيد من التفاعل معك يرجى ارسال طلب زواج';
                } else {
                    $title = 'زفاف ترحب بك';
                    // $message = 'ابحث عن أيقونة وكلاء زفاف ستجد فيها بيانات التواصل مع الوكيل تواصل معه للاشتراك في باقات زفاف المخفضة.';
                    // $message = 'أهلا بك علي زفاف..تم تفعيل باقتك التجريبية.تهانينا';
                    $message = 'لمزيد من التفاعل معك اشترك الآن في العضوية البلاتينية';
                }
                $params['title'] = $title;
                $params['message'] = $message;
                $params['multi'] = 1;
                $params['notificaionsTokens'] = "$deviceToken";
                $services = new Services($this->container);

                $services->sendNotification($params);
                $sql = "insert into notifications (otherId,notiType,title,message) values($lastInsertId,0,'$title','$message')";
                $sth = $this->db->prepare($sql);
                $sth->execute();
            } catch (\PDOException $e) {

                $responseData = array();
                $responseData['status'] = 'error';
                $responseData['errorMessage'] =  $e->getMessage();
                $responseData['errorCode'] = '3';
            }
        }
        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function getMyUpdates(Request $request, Response $response, $args, $internal = 0)
    {

        $userId = $request->getAttribute("userId");
        $notificationOpenDate = $args['notificationOpenDate'] ? $args['notificationOpenDate'] : date("Y-m-d h:i:sa");

        $sql = " select IFNULL(noti.newViews,0) as newViews,IFNULL(noti.newIntersest,0) 
                as newIntersest ,if(mes.newMessages=Null,0,mes.newMessages)as newMessages,
                if(post.newPosts=NULL,0,post.newPosts) as newPosts ,
                if(newChats.chatCount=NULL,0,newChats.chatCount) as newChats
                from
                (select sum(if(notiType=1,1,0)) as newViews ,sum(if(notiType=2,1,0)) as newIntersest  
                from notifications where otherId=$userId and publishDateTime >='$notificationOpenDate') as noti,
                (select count(id)as newMessages from messages where userId=$userId and readed=0) as mes,
                (select count(id)as newPosts from blog where postDateTime >='$notificationOpenDate' ) as post,
                (select count(id) as chatCount from chats
                where (user1Id = $userId or user2Id = $userId) and lastSender!=$userId and readed=1 
                and (if(user1Id=$userId,user1Active=1,user2Active=1))
                ) as newChats";
        //echo $sql;
        $responseData = array();
        try {
            $sth = $this->db->prepare($sql);

            $sth->execute();
            $data = $sth->fetchAll();

            if (sizeof($data) > 0) //user exists
            {
                $responseData['status'] = 'success';
                $responseData['rowsCount'] = 0;
                $responseData['data'] = $data;
            } else {
                $responseData['status'] = 'error';
                $responseData['rowsCount'] = 0;
                $responseData['errorMessage'] = 'no user error';
            }
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }
        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function search(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();


        $pageSize = isset($inputs['pageSize']) ? $inputs['pageSize'] : $this->pageSize;
        $page = isset($inputs['page']) ? $inputs['page'] : 0;
        $page *= $pageSize;
        if ($internal == 0) {
            $userGender = $request->getAttribute("userGender");
            // $orderBy = ' id desc';
            $orderBy = isset($inputs['orderBy']) ? $inputs['orderBy'] : 'lastaccess';
            $orderBy = $orderBy . " desc";
            $residentCountryId = $inputs['residentCountryId'];
            $nationalityCountryId = $inputs['nationalityCountryId'];
            $education = $inputs['education'];
            $financial = $inputs['financial'];
            $mariageStatues = $inputs['mariageStatues'];
            $mariageKind = $inputs['mariageKind'];
            $workField = $inputs['workField'];
            $ageFrom = $inputs['ageFrom'];
            $ageTo = $inputs['ageTo'];
            $cityId = $inputs['cityId'];
            $height = $inputs['height'];
            $weight = $inputs['weight'];
            $veil = $inputs['veil'];
            $userName = $inputs['userName'];
        }
        if ($internal == 1) {
            $pageSize = $args['pageSize'] ?? null;
            $userGender = $args['userGender'] ?? null;
            $latestresidentCountryId = $args['residentCountryId'] ?? null;

            // $orderBy = $args['orderBy'];

            $orderBy = "
            (CASE WHEN residentCountryId = '$latestresidentCountryId' and available>0 then lastAccess  END) desc,
            (CASE WHEN residentCountryId = '$latestresidentCountryId' and available=0 then lastAccess  END) desc,
            (CASE WHEN residentCountryId != '$latestresidentCountryId'  THEN lastAccess END) DESC";

            $userName = $inputs['userName'] ?? null;
            //            (CASE WHEN residentCountryId != '$latestresidentCountryId' or date(lastAccess)!=date(now()) THEN available END) DESC,

        }
        if ($internal == 2) //My saved search
        {
            $residentCountryId =  $args['residentCountryId'];
            $nationalityCountryId =  $args['nationalityCountryId'];
            $education =  $args['education'];
            $financial =  $args['financial'];
            $mariageStatues =  $args['mariageStatues'];
            $mariageKind =  $args['mariageKind'];
            $workField =  $args['workField'];
            $ageFrom =  $args['ageFrom'];
            $ageTo =  $args['ageTo'];
            $weightFrom =  $args['weightFrom'];
            $weightTo =  $args['weightTo'];
            $heightFrom =  $args['heightFrom'];
            $heightTo =  $args['heightTo'];
            $skinColor =  $args['skinColor'];
            $smoking =  $args['smoking'];
            $prayer =  $args['prayer'];
            $income =  $args['income'];
            $helath =  $args['helath'];
            $veil = $args['veil'];

            $page = $args['page'];
            $page *= $pageSize;
            $userGender = $args['userGender'];
            $orderBy = $args['orderBy'] . " desc";
        }
        $searchGender = ($userGender == 0) ? 1 : 0;

        $sql = " select users.id,packages.packageLevel,userName,age,gender,available,lastAccess,if(packageId=0,0,1) as packageId,residentCountryId,
                    countries1.nameAr as residentCountryName,nationalityCountryId,countries2.nameAr as nationalityCountryName,
                    cityId,cities.nameAr as cityName,lastAccess,mariageStatues
                    from users inner join countries as countries1 inner join countries as countries2
                    inner join cities inner join packages
                    on users.residentCountryId = countries1.id and users.nationalityCountryId = countries2.id
                    and users.cityId=cities.id  and users.packageId = packages.id
                    where  users.active=1 ";
        // $sql.="   and  (lastAccess BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND NOW() )  ";
        $sql .= ($userGender != -1) ? " and gender=$searchGender" : "";
        if ($internal == 2) //My saved search
        {
            $sql .= isset($residentCountryId) && ($residentCountryId > 0) ? " and residentCountryId IN ($residentCountryId)" : "";
            $sql .= isset($nationalityCountryId) && ($nationalityCountryId > 0) ? " and nationalityCountryId IN ($nationalityCountryId)" : "";
            $sql .= isset($education) && ($education > 0) ? " and education IN ($education)" : "";
            $sql .= isset($financial) && ($financial > 0) ? " and financial IN($financial)" : "";
            $sql .= isset($mariageStatues) && ($mariageStatues > 0) ? " and mariageStatues IN($mariageStatues)" : "";
            $sql .= isset($mariageKind) && ($mariageKind > 0) ? " and mariageKind IN($mariageKind)" : "";
            $sql .= isset($workField) && ($workField > 0) ? " and workField IN($workField)" : "";
            $sql .= isset($skinColor) && ($skinColor > 0) ? " and skinColor IN($skinColor)" : "";
            $sql .= isset($smoking) && ($smoking > 0) ? " and smoking IN($smoking)" : "";
            $sql .= isset($prayer) && ($prayer > 0) ? " and prayer IN($prayer)" : "";
            $sql .= isset($income) && ($income > 0) ? " and income IN($income)" : "";
            $sql .= isset($helath) && ($helath > 0) ? " and helath IN($helath)" : "";
            $sql .= isset($ageFrom) && ($ageFrom > 0) ? " and age>=$ageFrom" : "";
            $sql .= isset($ageTo) && ($ageTo > 0) ? " and age<=$ageTo" : "";
            $sql .= isset($weightFrom) && ($weightFrom > 0) ? " and weight>=$weightFrom" : "";
            $sql .= isset($weightTo) && ($weightTo > 0) ? " and weight<=$weightTo" : "";
            $sql .= isset($heightFrom) && ($heightFrom > 0) ? " and height>=$heightFrom" : "";
            $sql .= isset($heightTo) && ($heightTo > 0) ? " and height<=$heightTo" : "";
            $sql .= isset($veil) && ($veil > 0) ? " and veil=$veil" : "";
        } else // Normal search
        {
            $sql .= isset($userName) ? " and userName like '%$userName%'" : "";


            $sql .= isset($residentCountryId) && ($residentCountryId > 0) ? " and residentCountryId=$residentCountryId" : "";
            $sql .= isset($nationalityCountryId) && ($nationalityCountryId > 0) ? " and nationalityCountryId=$nationalityCountryId" : "";
            $sql .= isset($education) && ($education > 0) ? " and education=$education" : "";
            $sql .= isset($financial) && ($financial > 0) ? " and financial=$financial" : "";
            $sql .= isset($mariageStatues) && ($mariageStatues > 0) ? " and mariageStatues=$mariageStatues" : "";
            $sql .= isset($mariageKind) && ($mariageKind > 0) ? " and mariageKind=$mariageKind" : "";
            $sql .= isset($workField) && ($workField > 0) ? " and workField=$workField" : "";
            $sql .= isset($ageFrom) && ($ageFrom > 0) ? " and age>=$ageFrom" : "";
            $sql .= isset($ageTo) && ($ageTo > 0) ? " and age<=$ageTo" : "";
            $sql .= isset($cityId) && ($cityId > 0) ? " and cityId=$cityId" : "";
            $sql .= isset($weight) && ($weight > 0) ? " and weight>=$weight and weight<=$weight+10" : "";
            $sql .= isset($height) && ($height > 0) ? " and height>=$height and height<=$height+10" : "";
            $sql .= isset($veil) && ($veil > 0) ? " and veil=$veil" : "";
        }
        $sql .= " order by ";
        $sql .= isset($orderBy) ? "$orderBy" : "id desc";
        $sql .= "  limit $page,$pageSize ";

        //echo $sql;
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['morePages'] = ($pageSize > count($data)) ? 0 : 1;
            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function getMyNotifications(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();

        $pageSize =  $this->pageSize;;
        $page = isset($args['page']) ? $args['page'] : 0;
        $page *= $pageSize;

        $userId = $request->getAttribute("userId");
        $userGender = $request->getAttribute("userGender");

        $notificationtype = $args['notificationtype'];
        $lastChecked = $args['lastChecked'];

        $sql = "select residentCountryId  from users where id=$userId";
        $sth = $this->db->prepare($sql);
        $sth->execute();
        $data = $sth->fetchAll();
        $residentCountryId = $data[0]['residentCountryId'];


        $sql = " select notifications.*,userName,'' as profileImage,available,packages.packageLevel,
        lastAccess,packageId,age,gender from notifications 
        inner join users inner join packages 
        on notifications.userId = users.id and users.packageId = packages.id 
        where (publishDateTime BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND NOW() ) and 
        (fixed =1 or otherId=$userId ) and (notiGender=2 or notiGender=$userGender) and 
        (notifications.residentCountryId=0 or notifications.residentCountryId=$residentCountryId)";
        // fixed =1 or 
        $sql .= isset($notificationtype) && $notificationtype != '-' ? " and notiType=$notificationtype" : "";
        //$sql.= ")";
        //$sql.= isset($lastChecked)? " and publishDateTime>='$lastChecked'" : "";

        $sql .= " order by publishDateTime desc ";
        $sql .= "  limit $page,$pageSize ";

        // echo $sql;
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function updateMyPassword(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $password =  md5($inputs['password']);

        $sql = " update users set password='$password' where id=$userId";
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function requestChangePassword(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userName = $inputs['userName'];
        $tempPassword =  random_int(105980, 987456);

        $responseData = array();


        try {
            $sql = "select id,email from users where userName='$userName' || mobile ='$userName'";

            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();
            if (count($data) > 0) //user exist
            {
                $email = $data[0]['email'];
                $sql = " update users set tempPassword='$tempPassword',
                    tempPasswordExpire = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                    where userName='$userName' ";

                $sth = $this->db->prepare($sql);
                $sth->execute();
                $services = new Services($this->container);
                $message = 'استعمل الكود التالي لنموذج التحقق لتغيير كلمه المرور الخاصة بك <br> ' . $tempPassword;
                $title = 'كود نسيت كلمه المرور';
                $services->sendEmail($email, $title, $message);


                $responseData['status'] = 'success';
                $responseData['rowsCount'] = 1;
            } else {
                $responseData['status'] = 'error';
                $responseData['rowsCount'] = 0;
                $responseData['errorCode'] =  'User not exists';
            }
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function changePassword(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userName = $inputs['userName'];
        $tempPassword = $inputs['tempPassword'];
        $password =  md5($inputs['password']);

        $responseData = array();

        try {
            $sql = "select id from users where userName='$userName' and tempPassword='$tempPassword'
                    and tempPasswordExpire > NOW()";

            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();
            if (count($data) > 0) //user exist
            {

                $sql = " update users set password='$password' where userName='$userName' and tempPassword='$tempPassword'
                and tempPasswordExpire > NOW()";

                $sth = $this->db->prepare($sql);
                $sth->execute();

                $responseData['status'] = 'success';
                $responseData['rowsCount'] = 1;
            } else {
                $responseData['status'] = 'error';
                $responseData['rowsCount'] = 0;
                $responseData['errorCode'] =  'Code error or expired';
            }
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function changePasswordNew(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $mobile = $inputs['mobile'];
        $password =  md5($inputs['password']);

        $responseData = array();

        try {
            $sql = " update users set password='$password' where mobile='$mobile' ";

            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function updateMyMobile(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $mobile =  $inputs['mobile'];

        $sql = " update users set mobile='$mobile' where id=$userId";
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function deleteMyProfileImage(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");

        $sql = " update users set profileImage = NULL,tempProfileImage = NULL where id=$userId";
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function changeMyStatus(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $status =  $inputs['status'];
        if ($status < 3) //Normal change
            $sql = " update users set available=$status,lastAccess=NOW() where id=$userId";
        else //Socket change
            $sql = " update users set available=1,lastAccess=NOW() where id=$userId and available=0";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function updateMySettings(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $receiveNotification =  $inputs['notifications'];

        $contactsNationality =  isset($inputs['nationalities']) ? $inputs['nationalities'] : '-1';
        $contactResident =  isset($inputs['residents']) ? $inputs['residents'] : '-1';
        $contactAgesFrom =  isset($inputs['agesFrom']) ? $inputs['agesFrom'] : 18;
        $contactAgesTo =  isset($inputs['agesTo']) ? $inputs['agesTo'] : 70;

        $sql = " update users set receiveNotification=$receiveNotification ,
                contactsNationality='$contactsNationality',contactResident='$contactResident',
                contactAgesFrom =$contactAgesFrom,contactAgesTo=$contactAgesTo 
                 where id=$userId";
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
            $sql = " select receiveNotification,contactsNationality,contactResident,
                        contactAgesFrom,contactAgesTo from users where id=$userId";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();
            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function terminateMyAccount(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $mobile =  $inputs['mobile'];

        $sql = " update users set active=3 where id=$userId";
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function getMySettings(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");

        $sql = " select receiveNotification,contactsNationality,contactResident,
        contactAgesFrom,contactAgesTo from users where id=$userId";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();
            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function getUserDetails(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $id = $args['id'];
        // Tamer 483 Mona 381
        $sql = " select users.id,packages.packageLevel,userName,users.mobile,gender,if(allowImage=1,profileImage,'') as userImage,available,
                    packageId,residentCountryId,veil,
                    countries1.nameAr as residentCountryName,nationalityCountryId,countries2.nameAr as 
                    nationalityCountryName,
                    cityId,cities.nameAr as cityName,mariageStatues,mariageKind,age,kids,weight,height,
                    skinColor,smoking,religiosity,prayer,
                    education,financial,workField,job,income,helath,aboutMe,aboutOther,creationDate,lastAccess,
                    detectedCountry,deviceToken,IFNULL(userLists.ignoreList,0) as ignoreList,
                    IFNULL(userLists.interestList,0) as interestList,IFNULL(allowImageList.allowImage,0) as allowImage
                    ,IFNULL(userLists.requestImage,0) as requestImage,
                    myImage.requestMyImage,myImage.viewMyImage

                    ,IFNULL(userLists.requestMobile,0) as requestMobile
                    ,IFNULL(myMobile.requestMyMobile,0) as requestMyMobile,
                    myMobile.viewMyMobile

                    ,IFNULL(allowMobileList.allowMobile,0) as allowMobile

                    from users inner join countries as countries1 inner join countries as countries2
                    inner join cities inner join  (select userId,otherId,sum(if(listType=0,1,0)) as ignoreList ,
                    sum(if(listType=1,1,0)) as interestList ,sum(if(listType=3,1,0)) as requestImage
                    ,sum(if(listType=7,1,0)) as requestMobile 

                    from lists 
                    where userId=$userId and otherId=$id) as userLists 
                    inner join 
                    (select sum(if(listType=2,1,0)) as allowImage from lists 
                    where userId=$id and otherId=$userId and listType=2) as allowImageList 

                    inner join (select sum(if(listType=3 and userId=$id and otherId=$userId,1,0)) as requestMyImage, 
                    sum(if(listType=2 and userId=$userId and otherId=$id,1,0)) as viewMyImage
                    from lists) as myImage

                    inner join
                    (select sum(if(listType=8,1,0)) as allowMobile from lists
                    where userId=$id and otherId=$userId and listType=8) as allowMobileList

                    inner join (select sum(if(listType=7 and userId=$id and otherId=$userId,1,0)) as requestMyMobile,
                    sum(if(listType=8 and userId=$userId and otherId=$id,1,0)) as viewMyMobile from lists) as myMobile
                    inner join packages
                    on users.residentCountryId = countries1.id and users.nationalityCountryId = countries2.id
                    and users.cityId=cities.id  and users.packageId = packages.id
                    where users.id=$id";
        //echo $sql;
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['data'] = $data;

            //---Send Notification----
            $args['otherId'] = $id;
            $args['notiType'] = 1;
            $this->AddNotification($request, $response, $args);
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function getMyFavorites(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $pageSize = $this->pageSize;
        $page = isset($args['page']) ? $args['page'] : 0;
        $page *= $pageSize;

        $listType = $args['listType'];
        $lastChecked = $args['lastChecked'];


        $userId = $request->getAttribute("userId");

        $sql = " select lists.userId,packages.packageLevel,lists.otherId,lists.listType,lists.listDateTime,userName,gender,
                available,lastAccess,packageId,
                age,countries1.nameAr as residentCountryName,nationalityCountryId,
                countries2.nameAr as nationalityCountryName,
                cityId,cities.nameAr as cityName,lastAccess,mariageStatues  from lists 
                inner join users inner join countries as countries1 inner join countries as countries2
                inner join cities inner join packages
                on users.residentCountryId = countries1.id and users.nationalityCountryId = countries2.id
                and users.cityId=cities.id  and lists.otherId = users.id and users.packageId = packages.id
                where userId=$userId and listType=$listType ";
        $sql .= isset($lastChecked) ? " and listDateTime>='$lastChecked'" : "";
        $sql .= " order by lists.id desc  limit $page,$pageSize ";

        //echo $sql;




        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function addToMyFavorites(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $otherId =  $inputs['otherId'];
        $listType =  $inputs['listType'];
        if ($internal == 1)
            $listType =  $args['listType'];


        $ignored = $this->checkIgnored($userId, $otherId);
        if ($ignored == 1) {
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  'ignore list';
            return JsonResponse::withJson($response, json_encode($responseData), 200);
        }

        //0 general 1 view 2 interest 3 photorequest 4 photoapproved 5 photoRefused
        //---Send Notification----
        if ($listType == 1) {
            $args['otherId'] = $otherId;
            $args['notiType'] = 2;
            $this->AddNotification($request, $response, $args);
        }


        $sql0 = "delete from lists where userId=$userId and otherId=$otherId and ";
        if ($listType < 2)
            $sql0 .= " (listType=0 or listType=1)";
        else
            $sql0 .= " listType=2";

        $sql1 = "insert into lists (userId,otherId,listType) values($userId,$otherId,$listType)";


        $responseData = array();

        //echo $sql0;
        //echo $sql1;
        try {
            $sth = $this->db->prepare($sql0);
            $sth->execute();
            $sth = $this->db->prepare($sql1);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;

            //---update chat to readed if user added to ignore list---
            if ($listType == 0) {
                $sql0 = "update chats set readed =2 where (user1Id=$userId and user2Id=$otherId ) 
                                or (user2Id=$userId and user1Id=$otherId) ";
                $sth = $this->db->prepare($sql0);
                $sth->execute();
            }
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function removeFromFavorites(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $otherId =  $inputs['otherId'];
        $listType =  $inputs['listType'];
        if ($internal == 1)
            $listType =  $args['listType'];

        // $ignored = $this->checkIgnored($userId,$otherId);
        // if($ignored==1){
        //                     $responseData['status'] = 'error'; 
        //                     $responseData['errorCode'] =  'ignore list'; 
        //                     return JsonResponse::withJson($response, json_encode($responseData), 200);
        //     }

        $sql0 = "delete from lists where userId=$userId and otherId=$otherId and ";
        if ($listType < 2)
            $sql0 .= " (listType=0 or listType=1)";
        else
            $sql0 .= " listType=$listType";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql0);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function getPackages(Request $request, Response $response, $args, $internal = 0)
    {

        $responseData = array();

        try {


            $sql = " select * from packages where active=1 and countryId=0";
            $sql .= " order by validFor ";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function getPackages2(Request $request, Response $response, $args, $internal = 0)
    {
        $userId = $request->getAttribute("userId");

        $responseData = array();

        try {

            $sql = " select telesalesId,residentCountryId from users where users.id = $userId ";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $telesales = $sth->fetchAll();
            $telesalesId = $telesales[0]['telesalesId'];
            $countryId = $telesales[0]['residentCountryId'];

            $sql = " select * from packages where active=1 and countryId=0 ";
            $sql .= ($telesalesId > 0) ? " and discounted=1 " : " and discounted=0";
            $sql .= " order by usdValue desc ";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $sql = " select id,name,email,mobile,whats from agents where countryId=$countryId limit 1 ";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $agents = $sth->fetchAll();

            $sql = " select * from packages where active=1 and countryId=$countryId  ";
            $sql .= " order by usdValue desc ";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $agentPackages = $sth->fetchAll();


            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['data'] = $data;
            $responseData['agent'] = $agents[0];
            $responseData['agent']['agentPackages'] = $agentPackages;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function getPaymentTokens(Request $request, Response $response, $args, $internal = 0)
    {
        $userId = $request->getAttribute("userId");

        $sql = " select * from packages order by validFor ";
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $paymentTokens = array();
            foreach ($data as $package) {
                $packageId = $package['id'];
                $paymentValue =  $package['usdValue'];

                $now = new DateTime("Now");
                $future = new DateTime("now +30 minutes");
                $payload = [
                    "iat" => $now->getTimeStamp(),
                    "exp" => $future->getTimeStamp(),
                    "userId" => $userId,
                    "packageId" => $packageId,
                    "paymentValue" => $paymentValue
                ];


                $token = JWT::encode($payload, $this->privateKey, "RS256");
                $package['token'] = $token;
                array_push($paymentTokens, $package);
            }
            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['data'] = $paymentTokens;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function purchasePackage(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();
        if ($internal == 1) {
            $userId = $args["userId"];
            $packageId =  $args['packageId'];
            $paymentRefrence =  $args['paymentRefrence'];
            $paymentValue =  $args['paymentValue'];
            $productId =  '';
            $transactionId =  $args['transactionId'];
            $purchaseToken =  '';
        } else {
            $userId = $request->getAttribute("userId");
            $packageId =  $inputs['packageId'];
            $paymentRefrence =  $inputs['paymentRefrence'];
            $paymentValue =  $inputs['paymentValue'];

            $productId =  $inputs['productId'];
            $transactionId =  $inputs['transactionId'];
            $purchaseToken =  $inputs['purchaseToken'];

            //-----check for duplicate purchase---
            $sql = " select id from purchases where userId = $userId and paymentRefrence = '$paymentRefrence'
                    and transactionId = '$transactionId'";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $duplicatePurchase = $sth->fetchAll();

            $sql = " select id from packages where id = $packageId and iapId = '$productId'
                    and usdValue = '$paymentValue' and active=1";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $purchasedPackage = $sth->fetchAll();

            if (count($duplicatePurchase) > 0 || count($purchasedPackage) == 0) {
                $responseData = array();
                $responseData['status'] = 'error';
                $responseData['errorCode'] =  'duplicate purchase/wrong package';
                return JsonResponse::withJson($response, json_encode($responseData), 200);
            }
        }
        if ($paymentValue == 25) $paymentValue = 20;
        //---Verify Appl receipt 
        if ($paymentRefrence == 'applepay') {
            $body = '{"receipt-data":"' . $purchaseToken . '"}';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://buy.itunes.apple.com/verifyReceipt');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER,     array('application/json; charset=utf-8'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $liveResult = curl_exec($ch);
            curl_close($ch);
            $liveResult = json_decode($liveResult, true);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://sandbox.itunes.apple.com/verifyReceipt');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER,     array('application/json; charset=utf-8'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $sanboxResult = curl_exec($ch);
            curl_close($ch);
            $sanboxResult = json_decode($sanboxResult, true);

            // echo("Live : " . $liveResult['status']);
            // echo("Sandbox : " . $sanboxResult['status']);
            //------


            if ($liveResult['status'] != '0' && $sanboxResult['status'] != '0') {

                $responseData = array();
                $responseData['status'] = 'error';
                $responseData['errorCode'] =  'Apple receipt not validated';
                return JsonResponse::withJson($response, json_encode($responseData), 200);
            }
        }

        //--select telesales ----
        $sql = " select telesalesId from users where users.id = $userId ";
        $sth = $this->db->prepare($sql);
        $sth->execute();
        $telesales = $sth->fetchAll();
        $telesalesId = $telesales[0]['telesalesId'];


        $sql = "insert IGNORE into purchases (userId,packageId,paymentRefrence,paymentValue,productId,
                transactionId,purchaseToken,telesalesId) 
            values($userId,$packageId,'$paymentRefrence',$paymentValue,
                '$productId','$transactionId','$purchaseToken','$telesalesId')";

        $sql1 = "update users set PreviousPackageRenewDate = packageRenewDate ,previousPackageId = packageId,
                 packageRenewDate = DATE_ADD(if(users.packageId=0,CURDATE(),packageRenewDate),  
             INTERVAL (select validFor from packages where id=$packageId) DAY) ,packageId=$packageId  where users.id=$userId";
        $sql2 = "delete from failedPurchases where userId=$userId";
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $sth = $this->db->prepare($sql1);
            $sth->execute();
            $sth = $this->db->prepare($sql2);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function tryPurchasePackage(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $packageId =  $inputs['packageId'];
        $payMethod =  $inputs['payMethod']; //0 Apple - 1 Android - 2 Paypal

        $sql = "insert  into failedPurchases (userId,packageId,purchaseDayTime,payMethod) 
            values($userId,$packageId,NOW(),$payMethod)";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function getPostsCategories(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();


        $sql = "select * from blogCats where id >1 and active=1";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function getPosts(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $catId =  $args['catId'];
        $pageSize = $this->pageSize;
        $page = isset($args['page']) ? $args['page'] : 0;
        $page *= $pageSize;

        $sql = "select * from blog where active=1 ";
        if (isset($catId) && $catId != 0) $sql .= " and catId=$catId  ";
        $sql .= " order by id desc  limit $page,$pageSize ";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function getPostDetails(Request $request, Response $response, $args, $internal = 0)
    {
        $id =  $args['id'];
        $sql = "select * from blog where id=$id ";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function getSuccessStories(Request $request, Response $response, $args, $internal = 0)
    {
        $pageSize = isset($args['pageSize']) ? $args['pageSize'] : $this->pageSize;
        $page = isset($args['page']) ? $args['page'] : 0;
        $page *= $pageSize;


        $sql = "select successStories.id,storyDate,story,husb.userName as husName,wife.userName as wifName from 
            successStories 
            inner join users as husb inner join users as wife
            on successStories.husId =husb.id and successStories.wifId = wife.id
            where successStories.active=1 order by successStories.id desc limit $page,$pageSize";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function getStoryDetails(Request $request, Response $response, $args, $internal = 0)
    {
        $id =  $args['id'];

        $sql = "select storyDate,story,husb.userName as husName,wife.userName as wifName from successStories 
            inner join users as husb inner join users as wife
            on successStories.husId =husb.id and successStories.wifId = wife.id
            where successStories.id=$id  ";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function addSuccessStory(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $userGender = $request->getAttribute("userGender");
        $story =  $inputs['story'];

        $otherUserName =  $inputs['otherUserName'];
        $sql = "select id from users where userName='$otherUserName'";



        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();
            if (count($data) > 0) //user exist
            {
                $otherId = $data[0]['id'];
                if ($userGender == 0) { //man
                    $husbId = $userId;
                    $wifId = $otherId;
                } else {
                    $husbId = $otherId;
                    $wifId = $userId;
                }
                $sql = "insert into successStories(husId,wifId,story)values($husbId,$wifId,'$story')";

                $sth = $this->db->prepare($sql);
                $sth->execute();

                $responseData['status'] = 'success';
                $responseData['rowsCount'] = 1;
            } else {
                $responseData['status'] = 'error';
                $responseData['rowsCount'] = 0;
                $responseData['errorCode'] =  ' user not exists';
            }
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function getMessagesList(Request $request, Response $response, $args, $internal = 0)
    {
        $userId = $request->getAttribute("userId");
        $userGender = $request->getAttribute("userGender");




        $sql = "select residentCountryId  from users where id=$userId";
        $sth = $this->db->prepare($sql);
        $sth->execute();
        $data = $sth->fetchAll();
        $residentCountryId = $data[0]['residentCountryId'];

        $sql = "select messages.id,userId,messageDateTime,reasonId,title,message,userReaded as readed,
                owner,image,adminImage,otherId,if(reply='',NULL,reply) as reply 
                from messages inner join users on
                messages.userId = users.id
                 where   
                  (messageDateTime BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND NOW() ) and 

                 (userId=$userId or userId=0) and 
                ( messages.residentCountryId=$residentCountryId or messages.residentCountryId=0) 
                and (messages.mesGender = $userGender or mesGender=2)

                order by messageDateTime desc";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            for ($i = 0; $i < count($data); $i++) {
                //For HTML Messages
                $message = nl2br($data[$i]['message']);
                $message = preg_replace("/<a /", "<a style='  color: blue;'", $message);
                $message = preg_replace("/\/public\/uploads/", "https://www.zefaaf.net/public/uploads", $message);


                $data[$i]['message'] = "<b>" . $message . "</b>";

                //For old messages
                // $message = strip_tags($data[$i]['message']);
                // $message = strip_tags($data[$i]['message']);
                // $message = preg_replace("/&nbsp;/", " ", $message);

                $data[$i]['message'] = $message;
            }

            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function getMessageDetails(Request $request, Response $response, $args, $internal = 0)
    {
        $userId = $request->getAttribute("userId");
        $id =  $args['id'];
        $userGender = $request->getAttribute("userGender");

        $sql = "select residentCountryId  from users where id=$userId";
        $sth = $this->db->prepare($sql);
        $sth->execute();
        $data = $sth->fetchAll();
        $residentCountryId = $data[0]['residentCountryId'];


        $sql = "select * from messages where 
        (messageDateTime BETWEEN DATE_SUB(NOW(), INTERVAL 30 DAY) AND NOW() ) and 

       (userId=$userId or userId=0) and 
      ( messages.residentCountryId=$residentCountryId or messages.residentCountryId=0) 
      and (messages.mesGender = $userGender or mesGender=2)
        and id=$id";
        $sql1 = "update messages set userReaded =1 where userId=$userId and id=$id";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();
            $sth = $this->db->prepare($sql1);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $message = nl2br($data[0]['message']);
            $message = preg_replace("/\/public\/uploads/", "https://www.zefaaf.net/public/uploads", $message);

            $data[0]['message'] = "<b>" . $message . "</b>";

            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function testSendEmail(Request $request, Response $response, $args, $internal = 0)
    {
        $services = new Services($this->container);
        $email = "mash@dreamsoft-it.com";
        $title = "Test";
        $message = "Test html message";

        $fileUploaded = $services->sendEmail($email, $title, $message);
    }
    public function sendMessage(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $reasonId =  $inputs['reasonId'];
        $otherId =  isset($inputs['otherId']) ? $inputs['otherId'] : 0;
        $title =  $inputs['title'];
        $message =  $inputs['message'];
        $imageFile =  $inputs['imageFile'];

        $image = '';

        $uploadedFiles = $request->getUploadedFiles();
        if (!empty($uploadedFiles['attachment'])) {
            $services = new Services($this->container);
            $fileUploaded = $services->upload($request, $response, $args, true);
            $image = $fileUploaded['fileName'];
        }
        if (isset($imageFile))
            $image = $imageFile;

        $sql = "insert into messages(userId,reasonId,title,message,otherId,image) 
                values($userId,$reasonId,'$title','$message',$otherId,'$image')";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
            $responseData['id'] = $this->db->lastInsertId();
            $responseData['title'] = $title;
            $responseData['message'] = $message;
            $responseData['image'] = $image;
            $responseData['attachment'] = $image;

            $responseData['reasonId'] = $reasonId;
            $responseData['otherId'] = $otherId;

            $services = new Services($this->container);
            $services->sendEmail('support@zefaaf.net', 'رساله من عضو', $message);
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }



    public function replyMessage(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $id =  $inputs['id'];
        $reply =  $inputs['reply'];

        $imageFile =  $inputs['imageFile'];

        $image = '';

        $uploadedFiles = $request->getUploadedFiles();
        if (!empty($uploadedFiles['attachment'])) {
            $services = new Services($this->container);
            $fileUploaded = $services->upload($request, $response, $args, true);
            $image = $fileUploaded['fileName'];
        }
        if (isset($imageFile))
            $image = $imageFile;


        $sql = "update  messages set reply ='$reply',image='$image' where id=$id";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function requestPhoto(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $otherId =  $inputs['otherId'];

        $ignored = $this->checkIgnored($userId, $otherId);
        if ($ignored == 1) {
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  'ignore list';
            return JsonResponse::withJson($response, json_encode($responseData), 200);
        }

        $sql = "REPLACE into lists (userId,otherId,listType) values($userId,$otherId,3)";
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            //---Send Notification----
            $args['otherId'] = $otherId;
            $args['notiType'] = 3;
            $this->AddNotification($request, $response, $args);
            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function cancelRequestPhoto(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $otherId =  $inputs['otherId'];


        $sql = "delete from lists where userId =$userId  and otherId = $otherId and listType =3";
        $sql1 = "delete from lists where userId = $otherId  and otherId = $userId and listType =2";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function replyPhoto(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $otherId =  $inputs['otherId'];
        $statues =  $inputs['statues']; //4 approved 5 refused

        try {
            //  $sql = "REPLACE into notifications (userId,otherId,notiType) values($userId,$otherId,$statues)";
            if ($statues == 4) { //Approved
                $args['listType'] = '2';
                $this->addToMyFavorites($request, $response, $args, 1);
                //---Send Notification----
                $args['otherId'] = $otherId;
                $args['notiType'] = 4;
                $this->AddNotification($request, $response, $args);
            } else { //declined-removed
                $args['listType'] = '2';
                $this->removeFromFavorites($request, $response, $args, 1);
                //---Send Notification----
                $args['otherId'] = $otherId;
                $args['notiType'] = 5;
                $this->AddNotification($request, $response, $args);
            }
            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function uploadMyPhoto(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");


        //-----Send Notification to app and web if your can upload image----

        $sql = "select deviceToken,webToken,packageId from users where id=$userId";
        $sth = $this->db->prepare($sql);
        $sth->execute();
        $data = $sth->fetchAll();

        if ($data[0]['packageId'] == 0) {
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  '1';
            $responseData['error'] =  "You can't upload photo";
            return JsonResponse::withJson($response, json_encode($responseData), 200);
        }


        $deviceToken = $data[0]['deviceToken'];
        $webToken = $data[0]['webToken'];



        // ---Insert into Notifications -----
        $message = 'صورتك الشخصية بانتظار الموافقة';
        $title = 'صورتك الشخصية';
        $sql = "insert into notifications (otherId,notiType,title,message) values($userId,0,'$title','$message')";
        $sth = $this->db->prepare($sql);
        $sth->execute();

        $services = new Services($this->container);
        $params = array();
        $params['privateData'] = '{"type":"0"}';
        $params['title'] = $title;
        $params['message'] = $message;
        $params['multi'] = 1;

        $params['notificaionsTokens'] = "$deviceToken,$webToken";
        $services->sendNotification($params);

        $sql = "update users set tempProfileImage =:image ,photoUploadeDate = NOW() where id=$userId";
        $responseData = array();

        try {
            $services = new Services($this->container);

            $fileUploaded = $services->upload($request, $response, array(), true);
            $sth = $this->db->prepare($sql);
            $sth->bindParam(':image', $fileUploaded['fileName']);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
            $responseData['fileName'] = $fileUploaded['fileName'];
            $responseData['filePath'] = $fileUploaded['filePath'] . "/small";
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function AddNotification(Request $request, Response $response, $args)
    {
        $userId = $request->getAttribute("userId");
        $userName = $request->getAttribute("userName");

        $otherId =  $args['otherId'];
        $notiType =  $args['notiType'];

        try {
            //---Insert into Notifications -----
            $sql = "REPLACE into notifications (userId,otherId,notiType) values($userId,$otherId,$notiType)";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            //---Select device token and send Notification -----

            $sql = "select deviceToken,webToken from users where id=$otherId";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $deviceToken = $data[0]['deviceToken'];
            $webToken = $data[0]['webToken'];
            //-----Send Notification to app and web ----
            $services = new Services($this->container);
            $params = array();
            $params['privateData'] = '{"type":"' . $notiType . '","id":"' . $userId . '"}';
            $params['topic'] = null;

            //0 general 1 view 2 interest 3 photorequest 4 photoapproved 5 photoRefused
            switch ($notiType) {
                case 0:
                    $params['title'] = '$title';
                    $params['message'] = '$message';
                    $params['topic'] = 'all';
                    break;
                case 1:
                    $params['title'] = 'مشاهده جديدة لحسابك ';
                    $params['message'] = 'قام ' . $userName . ' بمشاهدة حسابك ..اظغط لمشاهده التفاصيل';
                    $params['multi'] = 1;
                    break;
                case 2:
                    $params['title'] = 'تم إضافتك إلى قائمة الإعجاب';
                    $params['message'] = 'قام ' . $userName . ' باضافتك لقائمة الإعجاب الخاصة به';
                    $params['multi'] = 1;

                    break;
                case 3:
                    $params['title'] = 'طلب مشاهدة صورتك الشخصية ';
                    $params['message'] = 'قام ' . $userName . '  بطلب مشاهدة صورتك الشخصية';
                    $params['multi'] = 1;

                    break;
                case 4:
                    $params['title'] = 'تم الرد على طلبك لمشاهدة الصورة الشخصية ';
                    $params['message'] = 'قام ' . $userName . '  بالموافقة على طلبك لمشاهدة صورته الشخصية';
                    $params['multi'] = 1;

                    break;
                case 5:
                    $params['title'] = 'تم الرد على طلبك لمشاهدة الصورة الشخصية ';
                    $params['message'] = 'قام ' . $userName . ' برفض طلبك لمشاهدة صورته الشخصية';
                    $params['multi'] = 1;

                    break;

                case 7:
                    $params['title'] = 'طلب مشاهدة رقم موبايلك ';
                    $params['message'] = 'قام ' . $userName . '  بطلب مشاهدة رقمك ';
                    $params['multi'] = 1;

                    break;

                case 8:
                    $params['title'] = 'تم الرد على طلبك لمشاهدة رقم الموبايل ';
                    $params['message'] = 'قام ' . $userName . ' بقبول طلبك لمشاهدة رقمه ';
                    $params['multi'] = 1;

                    break;

                case 9:
                    $params['title'] = 'تم الرد على طلبك لمشاهدة رقم الموبايل ';
                    $params['message'] = 'قام ' . $userName . ' برفض طلبك لمشاهدة رقمه ';
                    $params['multi'] = 1;

                    break;
            }
            $params['notificaionsTokens'] = "$deviceToken,$webToken";
            if ($notiType == 1) return;
            $responseData = $services->sendNotification($params);
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        // return JsonResponse::withJson($response, json_encode($responseData), 200);



    }

    public function testNotification(Request $request, Response $response, $args)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $userDeviceToken = $request->getAttribute("userDeviceToken");


        $responseData = array();

        try {
            $services = new Services($this->container);

            $params = array();
            $params['message'] = isset($inputs['message']) ? $inputs['message'] : 'Test Message';
            $params['title'] = isset($inputs['title']) ? $inputs['title'] : 'Test Title';
            $params['multi'] = $inputs['multi'];
            $params['notificaionsTokens'] = isset($inputs['notificaionsTokens']) ? $inputs['notificaionsTokens'] : $userDeviceToken;
            $params['topic'] = $inputs['topic'];
            $params['privateData'] = isset($inputs['privateData']) ? $inputs['privateData'] : '{"type":"0"}';

            $responseData = $services->sendNotification($params);
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function getMyChatsList(Request $request, Response $response, $args, $internal = 0)
    {
        $userId = $request->getAttribute("userId");

        $sql = "select chats.id,lastMessagetime,lastMessage,lastMessageType,lastSender,readed,
                if(lastSender!=$userId and readed=1,1,0) as new,
                @otherId :=if(user1Id=$userId,user2Id,user1Id) as otherId,
                if(
                    (select  sum(if(listType=2,1,0)) as allowImage from lists
                    where userId=@otherId and otherId=$userId and listType=2)
                    
                    =1,if(user1Id=$userId,users2.profileImage,users1.profileImage),'') as userImage,

                if(user1Id=$userId,users2.userName,users1.userName) as otherName,
                if(user1Id=$userId,users2.detectedCountry,users1.detectedCountry) as detectedCountry,
                if(user1Id=$userId,users2.available,users1.available) as available,
                if(user1Id=$userId,users2.lastAccess,users1.lastAccess) as lastAccess,
                if(user1Id=$userId,users2.packageId,users1.packageId) as packageId,
                if(user1Id=$userId,packages2.packageLevel,packages1.packageLevel) as packageLevel

        
                from chats inner join users as users1 on chats.user1Id = users1.id
                inner join users as users2 inner join packages as packages1 inner join packages as packages2
                
                on chats.user2Id = users2.id and users1.packageId = packages1.id and users2.packageId = packages2.id 
                  
                where  lastMessage IS NOT NULL and (user1Id = $userId or user2Id = $userId) and 
                (if(user1Id=$userId,user1Active=1,user2Active=1))
                order by lastMessagetime desc";


        $responseData = array();
        //echo $sql;
        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function hideChat(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $chatId =  $inputs['chatId'];

        // $sql = "update chats SET user1Active = if(user1Id=$userId,0,user1Active) ,
        //         user2Active = if(user2Id=$userId,0,user2Active) where id=$chatId";
        // $sql = "update chats SET user1Active = 0 ,user2Active = 0,active=0 where id=$chatId";
        $sql = "delete from chats  where id=$chatId";

        $responseData = array();
        //echo $sql;
        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $sql = "update chatMessages SET active=0 where chatId=$chatId";

            $responseData['status'] = 'success';
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function hideAllChats(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");

        // $sql = "update chats SET user1Active = if(user1Id=$userId,0,user1Active) ,
        //         user2Active = if(user2Id=$userId,0,user2Active) where id=$chatId";
        // $sql = "update chats SET user1Active = 0 ,user2Active = 0,active=0 where user1Id=$userId  or user2Id=$userId ";
        $sql = "delete from chats  where user1Id=$userId  or user2Id=$userId ";

        $responseData = array();
        //echo $sql;
        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function deleteChatMessage(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $id =  $inputs['id'];



        $responseData = array();
        //echo $sql;
        try {
            //---get deleted message first---
            $sql = "select * from chatMessages where senderId=$userId and  id=$id";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $deletedMessage = $sth->fetchAll();

            $message = $deletedMessage[0]['message'];
            $chatId = $deletedMessage[0]['chatId'];

            $sql = "delete from chatMessages where senderId=$userId and  id=$id";
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $sql = "update chats set lastMessageTime=Now(),lastMessage = 'تم حذف الرسالة'
                where id=$chatId and lastSender=$userId and lastMessage='$message'";
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function openChat(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $otherId =  $inputs['otherId'];
        $reverse =  $inputs['reverse'];

        $sql1 = "select chats.id from chats where (user1Id = $userId and user2Id = $otherId) 
                or (user1Id = $otherId and user2Id = $userId) and chats.active=1";

        $sql2 = "insert into chats (user1Id,user2Id) values($userId,$otherId)";

        $sql3 = "select users.id,userName,packageLevel,mariageKind,gender,if(allowImage=1,profileImage,'') as userImage,available,lastAccess,
        DATE_FORMAT(lastAccess, '%Y-%m-%d') as lastAccessDate,
                    DATE_FORMAT(lastAccess,'%H:%i:%s') as lastAccessTime,
                    packageId,residentCountryId,
                    countries1.nameAr as residentCountryName,nationalityCountryId,countries2.nameAr as 
                    nationalityCountryName,
                    cityId,cities.nameAr as cityName,detectedCountry,
                    IFNULL(allowImageList.allowImage,0) as allowImage
                    ,IFNULL(userLists.requestImage,0) as requestImage
                    from users inner join countries as countries1 inner join countries as countries2
                    inner join cities inner join  (select userId,otherId,sum(if(listType=0,1,0)) as ignoreList ,
                    sum(if(listType=1,1,0)) as interestList ,sum(if(listType=3,1,0)) as requestImage from lists 
                    where userId=$userId and otherId=$otherId) as userLists 
                    inner join 
                    (select sum(if(listType=2,1,0)) as allowImage from lists 
                    where userId=$otherId and otherId=$userId and listType=2) as allowImageList 
                    inner join packages 
                    on users.residentCountryId = countries1.id and users.nationalityCountryId = countries2.id
                    and users.cityId=cities.id  and users.packageId = packages.id
                    where users.id=$otherId";
        // echo $sql3;  
        $sql4 = " select id,message,type,messageTime,DATE_FORMAT(messageTime, '%Y-%m-%d') as messageDate,
                    DATE_FORMAT(messageTime,'%H:%i:%s') as messageTimeOnly ,if(senderId=$userId,0,1) as owner,readed,
                    parent,parentMessage,parentType,played,voiceTime
                    from chatMessages where  chatId=:id order by id desc limit 0,100";


        //---
        $sql6 = "select * from lists where otherId=$userId and userId=$otherId and listType=0";
        $sql0 = "select gender,packageLevel from users inner join packages 
                    on users.packageId = packages.id
                    where users.id=$userId";

        $responseData = array();

        try {
            //get other details...
            $sth = $this->db->prepare($sql3);
            $sth->execute();
            $otherDetails = $sth->fetchAll();
            $responseData['otherDetails'] = $otherDetails;
            $mariageKind = $otherDetails[0]['mariageKind'];

            //---get user details...
            $sth = $this->db->prepare($sql0);
            $sth->execute();
            $data = $sth->fetchAll();

            $packageLevel = $data[0]['packageLevel'];

            //---check for man and package misplised with mariageking ....
            $userGender = $data[0]['userGender'];
            if ($userGender == 0 && $packageLevel < 4 && ($mariageKind == '183' || $mariageKind == '185')) {
                $responseData['status'] = 'error';
                $responseData['errorCode'] =  'package5';
                return JsonResponse::withJson($response, json_encode($responseData), 200);
            }

            if ($userGender == 0 && $packageLevel < 3 && ($mariageKind == '185' || $mariageKind == '184' || $mariageKind == '6')) {
                $responseData['status'] = 'error';
                $responseData['errorCode'] =  'package4';
                return JsonResponse::withJson($response, json_encode($responseData), 200);
            }

            //--Check if free return with error...
            if ($packageLevel == 0) {
                $responseData['status'] = 'error';
                $responseData['errorCode'] =  'free package';
                return JsonResponse::withJson($response, json_encode($responseData), 200);
            }
            //---check for ignore list ....
            $ignored = $this->checkIgnored($userId, $otherId);
            if ($ignored == 1) {
                $responseData['status'] = 'error';
                $responseData['errorCode'] =  'ignore list';
                return JsonResponse::withJson($response, json_encode($responseData), 200);
            }
            //--
            $sth = $this->db->prepare($sql1);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';

            if (count($data) == 0) { // New chat               
                $sth = $this->db->prepare($sql2);
                $sth->execute();
                $chatId = $this->db->lastInsertId();
            } else
                $chatId = $data[0]['id'];


            $sth = $this->db->prepare($sql4);
            $sth->bindParam(':id', $chatId);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['chatId'] = $chatId;
            $responseData['rowsCount'] = count($data);

            $today = date("Y-m-d");
            $responseData['data'] = $this->prepareChatRecords($today, $data, $reverse);

            //-- set readed to true
            $sql5 = "update chats set readed = 2 where lastSender != $userId and id=$chatId";
            $sql6 = "update chatMessages set readed = 2 where senderId !=$userId and chatId=$chatId";

            // echo $sql5;
            // echo $sql6;
            $sth = $this->db->prepare($sql5);
            $sth->execute();
            $sth = $this->db->prepare($sql6);
            $sth->execute();
        } catch (\PDOException $e) {
            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function checkIgnored($userId, $otherId)
    {
        $sql1 = "select userIgnore.ignoreResult,contactsNationality,contactResident,contactAgesFrom,contactAgesTo,
            myData.nationalityCountryId ,myData.residentCountryId,myData.age,
            if(contactsNationality=-1 || contactsNationality=0,1,FIND_IN_SET(myData.nationalityCountryId,contactsNationality)) as 
            nationalityResult,
            if(contactResident=-1 || contactResident=0,1,FIND_IN_SET(myData.residentCountryId,contactResident)) as residentResult,
            if((myData.age>=contactAgesFrom && (myData.age<=contactAgesTo || contactAgesTo=-1 || contactAgesTo=0)),1,0) as ageResult   
            from users inner join 
            (select nationalityCountryId,residentCountryId,age from users where id=$userId) as myData
            inner join (select if(sum(lists.id)>0,0,1) as ignoreResult from lists 
            where otherId=$userId and userId=$otherId and listType=0) as userIgnore
            where id =$otherId ";

        $sql2 = "select userIgnore.ignoreResult,contactsNationality,contactResident,contactAgesFrom,contactAgesTo,
                myData.nationalityCountryId ,myData.residentCountryId,myData.age,
                if(contactsNationality=-1 ||contactsNationality=0,1,FIND_IN_SET(myData.nationalityCountryId,contactsNationality)) as 
                nationalityResult,
                if(contactResident=-1 || contactResident=0,1,FIND_IN_SET(myData.residentCountryId,contactResident)) as residentResult,
                if((myData.age>=contactAgesFrom && (myData.age<=contactAgesTo || contactAgesTo=-1 || contactAgesTo=0 )),1,0) as ageResult   
                from users inner join 
                (select nationalityCountryId,residentCountryId,age from users where id=$otherId) as myData
                inner join (select if(sum(lists.id)>0,0,1) as ignoreResult from lists 
                where otherId=$otherId and userId=$userId and listType=0) as userIgnore
                where id =$userId ";
        $ignored = 0;
        $responseData = array();

        //echo $sql1;
        //echo $sql2;
        //---
        try {
            //check first if ignored him
            $sth = $this->db->prepare($sql1);
            $sth->execute();
            $allowToContact = $sth->fetchAll();
            if (
                !$allowToContact[0]['nationalityResult'] or
                !$allowToContact[0]['residentResult'] or
                !$allowToContact[0]['ageResult'] or
                !$allowToContact[0]['ignoreResult']
            ) {
                $responseData['data'] = $allowToContact[0];
                $responseData['status'] = 'error';
                $responseData['errorCode'] =  'ignore list1';
                $ignored = 1;
                // echo "1";
            } else {
                //check second if he ignored me
                $sth = $this->db->prepare($sql2);
                $sth->execute();
                $allowToContact = $sth->fetchAll();
                if (
                    !$allowToContact[0]['nationalityResult'] or
                    !$allowToContact[0]['residentResult'] or
                    !$allowToContact[0]['ageResult'] or
                    !$allowToContact[0]['ignoreResult']
                ) {
                    $responseData['data'] = $allowToContact[0];
                    $responseData['status'] = 'error';
                    $responseData['errorCode'] =  'ignore list2';
                    // echo "2";
                    $ignored = 1;
                }
            }
            return $ignored;
        } catch (\PDOException $e) {
            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }
    }

    public function getMorechatMessages(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $chatId =  $args['chatId'];
        $reverse =  $args['reverse'];

        $page = ($args['page'] * 100) - 1;

        $sql = " select id,message,type,messageTime,DATE_FORMAT(messageTime, '%Y-%m-%d') as messageDate,
                    DATE_FORMAT(messageTime,'%H:%i:%s') as messageTimeOnly,if(senderId=$userId,0,1) as owner,readed,parent,
                    parentMessage,parentType,played,voiceTime
                    from chatMessages where chatId=$chatId order by id desc limit $page,101";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $today = $data[0]['messageDate'];
            $reovedItem = array_shift($data);
            // print_r( $reovedItem);     
            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            if (count($data) > 0)
                $responseData['data'] =  $this->prepareChatRecords($today, $data, $reverse);
            else
                $responseData['data'] = [];
        } catch (\PDOException $e) {
            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    function prepareChatRecords($today, $data, $reverse)
    {
        $chatMessages = array();
        $dateRow = [
            'id' => 0,
            'type' => -1,
            'owner' => -1,
            'messageDate' => $today,
            'message' => '',
            'messageTime' => $today,
            'messageTimeOnly' => '',
            'readed' => 0,
            'parent' => 0,
            'parentMessage' => 0,
            'parentType' => 0,
            'played' => 0

        ];
        $messageDate = $today;

        foreach ($data as $row) {
            if ($messageDate != $row['messageDate']) {
                $dateRow = [
                    'id' => 0,
                    'type' => -1,
                    'owner' => -1,
                    'messageDate' => $messageDate,
                    'message' => '',
                    'messageTime' => $messageDate,
                    'messageTimeOnly' => '',
                    'readed' => 0,
                    'parent' => 0,
                    'parentMessage' => 0,
                    'parentType' => 0,
                    'played' => 0

                ];
                array_push($chatMessages, $dateRow);
                $messageDate = $row['messageDate'];
            }
            array_push($chatMessages, $row);
        }
        //---Add date for last record

        $messageDate = $row['messageDate'];
        if (!$messageDate)
            $messageDate = date("Y-m-d");

        $dateRow = [
            'id' => 0,
            'type' => -1,
            'owner' => -1,
            'messageDate' => $messageDate,
            'message' => '',
            'messageTime' => $messageDate,
            'messageTimeOnly' => '',
            'readed' => 0,
            'parent' => 0,
            'parentMessage' => 0,
            'parentType' => 0,
            'played' => 0

        ];
        array_push($chatMessages, $dateRow);

        if ($reverse == 1) $chatMessages = array_reverse($chatMessages);

        return $chatMessages;
    }

    function convertArabicNumbers($string)
    {
        return strtr($string, array('۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9'));
    }

    public function checkForSpamChat(Request $request, Response $response, $args, $internal = 0, $numberLength = 1)
    {
        $message = $args['message'];
        $message = $this->convertArabicNumbers($message);
        $responseData = array();
        try {
            $sql = "select abuseKeywords from settings";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();
            $abuseKeyword = null;
            $abuseKeywords = explode("-", $data[0]['abuseKeywords']);
            foreach ($abuseKeywords as $word) {
                // if (preg_match('/\W*((?i)' . $word . '(?-i))\W*/', $message)) 
                // {
                //     $abuseKeyword = $word;
                //     break;
                // }
                if (strpos($message, $word) !== false) {
                    $abuseKeyword = $word;
                    break;
                }
            }
            //check for mobile numbers
            // if (preg_match('/(?<=[\s,.:;"\']|^)' . "([+]?[0-9][0-9]{".$numberLength.",20})" . '(?=[\s,.:;"\']|$)/', $message)) 
            preg_match('/(\s?(\d+)\s?){' . $numberLength . '}/', $message, $output_array);
            if (count($output_array) > 0)
                $abuseKeyword = 'mobile';

            //check for emails
            $pattern = "([_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,}))";
            if (preg_match('/(?<=[\s,.:;"\']|^)' . $pattern . '(?=[\s,.:;"\']|$)/', $message))
                $abuseKeyword = 'email';
            //check for special characters

            $illegal = "#$%^&*()+=[]';@,./{}|:<>?~_-";
            $abuseKeyword = (strpbrk($message, $illegal)) ? 'illegal' : $abuseKeyword;


            if ($abuseKeyword) {
                $responseData['status'] = 'error';
                $responseData['abuseFound'] = 1;
                $responseData['abuseKeyword'] = $abuseKeyword;
            } else {
                $responseData['status'] = 'success';
                $responseData['abuseFound'] = 0;
            }
        } catch (\PDOException $e) {
            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);


        //     $responseData['status'] = 'success';
        //     $responseData['abuseFound'] = 0;
        // if($internal)
        //     return $responseData;
        // else 

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function sendChatMessage(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $userName = $request->getAttribute("userName");


        $chatId =  $inputs['chatId'];
        $message =  $inputs['message'];
        $type =  $inputs['type'];
        $parent =  isset($inputs['parent']) ? $inputs['parent'] : 0;
        $parentMessage =  isset($inputs['parentMessage']) ? $inputs['parentMessage'] : '';
        $parentType =  isset($inputs['parentType']) ? $inputs['parentType'] : 0;
        $filePath =  $inputs['filePath'];
        $voiceTime =  $inputs['voiceTime'];

        $sql0 = "select if(user1Id=$userId,user2Id,user1Id) as otherId  from chats where id=$chatId";
        $sth = $this->db->prepare($sql0);
        $sth->execute();
        $data = $sth->fetchAll();
        $otherId = $data[0]['otherId'];
        $ignored = $this->checkIgnored($userId, $otherId);
        if ($ignored == 1) {
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  'ignore list';
            return JsonResponse::withJson($response, json_encode($responseData), 200);
        }

        //--check for reserved words ---
        if ($type == 0) {
            $args['message'] = $message;
            // $spam = $this->checkForSpamChat($request,  $response, $args, 1);
            // if ($spam['status'] == 'error')
            //     return JsonResponse::withJson($response, json_encode($spam), 200);
        }
        //----


        $sql = "insert into chatMessages(chatId,message,type,senderId,messageTime,parent,parentMessage,parentType,voiceTime)
                    values($chatId,:message,$type,$userId,NOW(),$parent,'$parentMessage',$parentType,'$voiceTime')";

        $sql1 = "update chats set lastMessageTime=Now(),lastMessage = :message,lastSender=$userId
                    ,lastMessageType = $type , readed = 1 ,voiceTime = '$voiceTime'  where id=$chatId";

        try {
            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;

            if ($type > 1) {
                if ($type == 2) //image
                    $args['folderName'] = 'chatResources/userImages/';
                if ($type == 3) //voice
                    $args['folderName'] = 'chatResources/userVoices/';

                if (!$filePath && $type == 3) {
                    $services = new Services($this->container);
                    $fileUploaded = $services->upload($request, $response, $args, true);
                    $message = "/" . $args['folderName'] . $fileUploaded['fileName'];
                } else
                    $message = $filePath;
                $responseData['type'] = $type;
                $responseData['message'] = $message;
            }

            $sth = $this->db->prepare($sql);
            $sth->bindParam(':message', $message);
            $sth->execute();
            $messageId = $this->db->lastInsertId();
            $responseData['messageId'] = $messageId;

            $sth = $this->db->prepare($sql1);
            if ($type == 1 || $type == 2) //image
                $message = 'ملصق';
            if ($type == 3) //voice
                $message = 'تسجيل صوتي';

            $sth->bindParam(':message', $message);
            $sth->execute();
            //---Select device token and send Notification -----

            $sql = "select deviceToken,webToken from users where id =(
                select if(user1Id=$userId,user2Id,user1Id) as userId from chats where id=$chatId)";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $deviceToken = $data[0]['deviceToken'];
            $webToken = $data[0]['webToken'];
            //-----Send Notification to app and web ----
            $services = new Services($this->container);
            $params = array();
            $params['privateData'] = '{"type":"6","id":"' . $chatId . '","chatUser":"' . $userName . '","userId":"' . $userId . '"}';
            $params['topic'] = null;


            $params['title'] = "رسالة جديدة من $userName ";
            $params['message'] = $message;
            $params['multi'] = 1;

            $params['notificaionsTokens'] = "$deviceToken,$webToken";
            $services->sendNotification($params);
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function confirmReadedChat(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $chatId =  $inputs['chatId'];
        //-- set readed to true
        $sql5 = "update chats set readed = 2 where lastSender != $userId and id=$chatId";
        $sql6 = "update chatMessages set readed = 2 where senderId !=$userId and chatId=$chatId";


        $responseData = array();

        try {

            // echo $sql5;
            // echo $sql6;
            $sth = $this->db->prepare($sql5);
            $sth->execute();
            $sth = $this->db->prepare($sql6);
            $sth->execute();
            $responseData['status'] = 'success';
        } catch (\PDOException $e) {
            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function confirmPlayChatRecord(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $chatId =  $inputs['chatId'];
        $messageId =  $inputs['messageId'];

        //-- set readed to true
        $sql = "update chatMessages set played = 1 where id =$messageId and chatId=$chatId";


        $responseData = array();

        try {

            $sth = $this->db->prepare($sql);
            $sth->execute();
            $responseData['status'] = 'success';
        } catch (\PDOException $e) {
            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function getStickers(Request $request, Response $response, $args, $internal = 0)
    {
        $stickersDir    = 'chatResources/stickers';
        $stickers = array_diff(scandir($stickersDir), array('.', '..', 'index.html'));


        $zefaafStickersDir    = 'chatResources/zefaafStickers';
        $zefaafStickers = array_diff(scandir($zefaafStickersDir), array('.', '..', 'index.html'));


        $responseData = array();

        try {
            $responseData['status'] = 'success';

            $responseData['stickersCount'] = count($stickers);
            $responseData['stickersDir'] = "/chatResources/stickers";
            $responseData['stickers'] = $stickers;

            $responseData['zefaafStickersCount'] = count($zefaafStickers);
            $responseData['zefaafStickersDir'] = "/chatResources/zefaafStickers";
            $responseData['zefaafStickers'] = $zefaafStickers;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function requestPay22(Request $request, Response $response, $args, $internal = 0)
    {
        $userId = $request->getAttribute("userId");
        $packageId =  $args['packageId'];

        $sql = "select * from packages where id=$packageId";

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $paymentValue =  $data[0]['usdValue'];
            $title =  $data[0]['title'];

            $now = new DateTime("Now");
            $future = new DateTime("now +30 days");
            $payload = [
                "iat" => $now->getTimeStamp(),
                "exp" => $future->getTimeStamp(),
                "userId" => $userId,
                "packageId" => $packageId,
                "paymentValue" => $paymentValue

            ];
            $responseData = array();
            $paymentToken = JWT::encode($payload, $this->privateKey, "RS256");


            // return JsonResponse::withJson($response, json_encode($responseData), 200);


            $paymentPage = '
                <html>
                    <head>
                        <title>goSell Demo</title>
                        <meta charset="utf-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0" />
                        <link rel="shortcut icon" href="https://goSellJSLib.b-cdn.net/v1.6.0/imgs/tap-favicon.ico" />
                        <link href="https://goSellJSLib.b-cdn.net/v1.6.0/css/gosell.css" rel="stylesheet" />
                    </head>
                    <body>
                        <script type="text/javascript" src="https://goSellJSLib.b-cdn.net/v1.6.0/js/gosell.js"></script>

                        <div id="root"></div>
                        <!-- <button id="openLightBox" onclick="goSell.openLightBox()">open goSell LightBox</button>
                        <button id="openPage" onclick="goSell.openPaymentPage()">open goSell Page</button> -->

                        <script>

                        goSell.config({
                        containerID:"root",
                        gateway:{
                            publicKey:"pk_test_CiX4sZg3Jf5MBnuN2oaeSjUH",
                        //publicKey:"pk_test_EtHFV4BuPQokJT6jiROls87Y",
                            merchantId: null,
                            language:"ar",
                            contactInfo:true,
                            supportedCurrencies:"all",
                            supportedPaymentMethods: "all",
                            saveCardOption:false,
                            customerCards: true,
                            notifications:"standard",
                            callback:(response) => {
                             console.log(response.callback.status);
                            },
                            onClose: () => {
                                console.log("closeFailed");
                               // window.flutter_inappwebview.callHandler("cancelPayment","closeReaason:exitButton");
                            },
                            onLoad:() => {
                                console.log("onLoad");
                                goSell.openLightBox();
                                },
                            backgroundImg: {
                            url: "imgURL",
                            opacity: "0.5"
                            },
                            labels:{
                                cardNumber:"Card Number",
                                expirationDate:"MM/YY",
                                cvv:"CVV",
                                cardHolder:"Name on Card",
                                actionButton:"Pay"
                            },
                            style: {
                                base: {
                                color: "#535353",
                                lineHeight: "18px",
                                fontFamily: "sans-serif",
                                fontSmoothing: "antialiased",
                                fontSize: "16px",
                                "::placeholder": {
                                    color: "rgba(0, 0, 0, 0.26)",
                                    fontSize:"15px"
                                }
                                },
                                invalid: {
                                color: "red",
                                iconColor: "#fa755a "
                                }
                            }
                        },
                        customer:{
                            id:null,
                            first_name: "First Name",
                            middle_name: "Middle Name",
                            last_name: "Last Name",
                            email: "demo@email.com",
                            phone: {
                                country_code: "965",
                                number: "99999999"
                            }
                        },
                        order:{
                            amount: ' . $paymentValue . ',
                            currency:"USD",
                            items:[{
                            id:1,
                            name:"' . $title . '",
                            quantity: "1",
                            amount_per_unit:"' . $paymentValue . '",
                            
                            total_amount: "' . $paymentValue . '"
                            }],
                            shipping:null,
                            taxes: null
                        },
                        transaction:{
                        mode: "charge",
                        charge:{
                            saveCard: false,
                            threeDSecure: true,
                            description: "اشتراك بموقع زفاف",
                            reference:{
                                transaction: "txn_0001",
                                order: "ord_0001"
                            },
                            metadata:{
                                "paymentToken":"' . $paymentToken . '"
                            },
                            receipt:{
                                email: false,
                                sms: true
                            },
                            redirect: "https//:api.zefaaf.net/thankyou.html",
                            post: "https://api.zefaaf.net/v1/mobile/confirmPayment",
                            }
                        }
                        });

                        </script>

                    </body>
                    </html>
                ';
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }
        $newResponse = $response->withHeader('Content-type', 'text/html');
        $body = $newResponse->getBody();
        $body->write($paymentPage);

        return $newResponse->withBody($body);
    }

    public function requestWebPay(Request $request, Response $response, $args, $internal = 0)
    {

        return $this->requestPay($request,  $response, $args, 1);
    }

    public function requestPay(Request $request, Response $response, $args, $internal = 0)
    {
        if ($internal)
            $userId = $args['userId'];
        else
            $userId = $request->getAttribute("userId");
        $packageId =  $args['packageId'];

        $sql = "select * from packages where id=$packageId";

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $paymentValue =  $data[0]['usdValue'];
            $title =  $data[0]['title'];

            // $now = new DateTime("Now");
            // $future = new DateTime("now +30 days");                        
            // $payload = [
            //     "iat" => $now->getTimeStamp(),
            //     "exp" => $future->getTimeStamp(),
            //     "userId" => $userId,
            //     "packageId" => $packageId,
            //     "paymentValue" => $paymentValue

            // ];
            // $responseData = array();
            // $paymentToken = JWT::encode($payload, $this->privateKey, "RS256");
            $paymentRefrence = "$userId:$packageId";

            // return JsonResponse::withJson($response, json_encode($responseData), 200);


            $paymentPage = '
                <!DOCTYPE html>
                <html>
                
                <head>
                    <title>Zefaaf Payment</title>
                    <meta charset="utf-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <link rel="icon" href="https://www.vapulus.com/favicon.ico" type="image/x-icon" />
                    <link href="https://getbootstrap.com/docs/3.3/dist/css/bootstrap.min.css" rel="stylesheet">
                    <script src="https://api.vapulus.com:1338/app/session/script?appId=80c799cf-95f4-4a27-a442-9e65d7a36e09"></script>
                    <style id="antiClickjack">
                        body {
                            display: none !important;
                        }
                    </style>
                    <style>
                        body {
                            min-height: 100vh;
                        }
                
                        .paymentContent {
                            max-width: 90%;
                            width: 400px;
                            border: 1px solid #ccc;
                            border-radius: 8px;
                            overflow: hidden;
                            position: absolute;
                            top: 50%;
                            left: 50%;
                            transform: translate(-50%, -50%);
                        }
                
                        .paymentContent .paymentHeader {
                            display: block;
                            text-align: start;
                            background: #f0f3f5;
                            border-bottom: 1px solid #ccc;
                        }
                
                        .paymentContent .paymentHeader img {
                            margin-top: 5px;
                            height: 40px;
                        }
                
                        .paymentContent .container {
                            padding: 14px 20px;
                            max-width: 100%;
                        }
                
                        .paymentContent .paymentHeader h1 {
                            font-size: 22px;
                            font-weight: 600;
                            margin: 0px;
                        }
                
                        .paymentContent .form-group {
                            margin-bottom: 8px;
                            display: inline-block;
                            width: 100%;
                        }
                
                        .paymentContent .col-md-8 {
                            width: 100% !important;
                        }
                
                        .paymentContent .form-group .control-label {
                            font-size: 17px;
                            font-weight: 600;
                            text-align: end;
                        }
                
                        .paymentContent .form-group>div>input,
                        .paymentContent iframe {
                            height: 48px;
                            width: 100%;
                            border-radius: 4px;
                            border: 1px solid #ccc;
                        }
                
                        .paymentContent .group {
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                        }
                
                        .paymentContent .group .form-group {
                            width: calc(50% - 5px);
                        }
                
                        .paymentContent .btn {
                            max-width: 100%;
                            min-width: 220px;
                            margin: auto;
                            display: block;
                            height: 44px;
                            font-size: 18px;
                            margin-top: 8px;
                            padding: 0px 10px 0px 10px;
                        }
                
                        .paymentContent .btn.disabled {
                            padding: 0px 40px 0px 10px;
                        }
                
                        .paymentContent .btn span {
                            height: 30px;
                            line-height: 30px;
                        }
                
                        .paymentContent .disabled .loader {
                            border: 3px solid #f3f3f3;
                            border-top: 3px solid #333;
                            border-radius: 50%;
                            width: 30px;
                            height: 30px;
                            animation: spin 1s linear infinite;
                            display: inline-block;
                            float: left;
                        }
                
                        @keyframes spin {
                            0% {
                                transform: rotate(0deg);
                            }
                
                            100% {
                                transform: rotate(360deg);
                            }
                        }
                    </style>
                </head>
                
                <body>
                    <div class="paymentContent">
                        <section class="text-center paymentHeader">
                            <div class="container">
                                <h1 class="jumbotron-heading">Zefaaf Payment</h1>
                                <img src="https://www.zefaaf.net/public/front/imgs/logo.png" />
                                                            </div>
                        </section>
                        <div class="container" id="myPage">
                            <div class="row">
                                <div class="contents col-12">
                                <fieldset>
                                <div class="form-group">
                                    <label class="col-md-8 control-label" for="cardNumber"> : رقم البطاقة</label>
                                    <div class="col-md-8">
                                        <input type="text" id="cardNumber" class="form-control input-md" value="" readonly />
                                                                                    </div>
                                    </div>
                                    <div class="form-group">
                                            <label class="col-md-8 control-label" for="cardMonth">: تاريخ انتهاء البطاقة</label>
                                            </div>
                                    <div class="group">
                                    
        
                                        <div class="form-group">
                                            <label class="col-md-8 control-label" for="cardMonth"> : شهر </label>
                                            <div class="col-md-8">
                                                <input type="text" id="cardMonth" class="form-control input-md" value="" />
                                              </div>
                                            </div>
                                            <div class="form-group">
                                                <label class="col-md-8 control-label" for="cardYear"> : سنة </label>
                                                <div class="col-md-8">
                                                    <input type="text" id="cardYear" class="form-control input-md" value="" />
                                                  </div>
                                                </div>
                                            </div>
        
                                            <div class="form-group">
                                                <label class="col-md-8 control-label" for="cardCVC"> :CVC رمز الأمان</label>
                                                <div class="col-md-8">
                                                    <input pattern="\d{3}" maxlength="3" type="tel" id="cardCVC" class="form-control input-md" value="" readonly />
                                                                                    </div>
                                                </div>
                                </fieldset>
                                    <button class="btn btn-primary " id="payButton" onclick="pay();">
                                                                        <div class="loader"></div>
                                                                        <span>ادفع الآن</span>
                                                                    </button>
                                     <button class="btn btn-success" id="toggleBtn" onclick="console.log(\'DECLINED\'); location.replace(\'https://zefaaf.net/failed/payment\');">
                                                                        تراجع 
                                                                    </button> 
                
                                </div>
                            </div>
                        </div>
                    </div>
                    <script type="text/javascript">
                        if (window.PaymentSession) {
                                                            PaymentSession.configure({
                                                                fields: {
                                                                    // ATTACH HOSTED FIELDS IDS TO YOUR PAYMENT PAGE FOR A CREDIT CARD
                                                                    card: {
                                                                        cardNumber: "cardNumber",
                                                                        securityCode: "cardCVC",
                                                                        expiryMonth: "cardMonth",
                                                                        expiryYear: "cardYear"
                                                                    }
                                                                },
                                                                callbacks: {
                                                                    initialized: function(err, response) {
                                                                        console.log("init....");
                                                                        console.log(err, response);
                                                                        console.log("/init.....");
                                                                        // HANDLE INITIALIZATION RESPONSE
                                                                    },
                                                                    formSessionUpdate: function(err, response) {
                                                                        console.log("update callback.....");
                                                                        console.log(err, response);
                                                                        console.log("/update callback....");
                                                                        if (response.statusCode) {
                                                                            if (200 == response.statusCode) {
                                                                                console.log("Session updated with data: " + response.data.sessionId);
                                                                                var xhr = new XMLHttpRequest();
                                                                                xhr.open("POST", "https://api.zefaaf.net/v1/mobile/vapPay", true);
                                                                                xhr.setRequestHeader("Content-Type", "application/json");
                                                                                xhr.send(JSON.stringify({
                                                                                    "sessionId": response.data.sessionId,
                                                                                    "paymentRefrence":"' . $paymentRefrence . '"
                
                                                                                }));
                                                                                xhr.onload = function() {
                                                                                    //console.log("HELLO")
                                                                                    console.log(this.responseText);
                                                                                    var result = JSON.parse(this.responseText);
                                                                                    console.log(result["action"]);
                                                                                    if (result["action"] == "pending") {
                                                                                        console.log(result);
                                                                                        location.replace(result["paymentUrl"]);
                                                                                    }  
                                                                                    if(result["action"] == "accepted"){
                                                                                    console.log("CAPTURED");
                                                                                    location.replace("https://zefaaf.net?success=true");
                                                                                    }
                                                                                    if(result["action"] == "Failed"){
                                                                                        console.log("DECLINED"); 
																						location.replace("https://zefaaf.net/failed/payment");
                                                                                    }
                                                                                }
                
                                                                            } else if (201 == response.statusCode) {
                                                                                console.log("Session update failed with field errors.");
                                                                                toggle();
                
                                                                                if (response.message) {
                                                                                    var field = response.message.indexOf("valid")
                                                                                    field = response.message.slice(field + 5, response.message.length);
                                                                                    console.log(field + " is invalid or missing.");
                                                                                    alert(field + " is invalid or missing.");
                                                                                }
                                                                            } else {
                                                                                console.log("Session update failed: " + response);
                                                                                toggle();
                
                                                                            }
                                                                        }
                                                                    }
                                                                }
                                                            });
                                                        } else {
                                                            alert("Fail to get app/session/script !\n\nPlease check if your appId added in session script tag in head section?")
                                                        }
                
                                                        function pay() {
                                                            toggle();
                                                            PaymentSession.updateSessionFromForm();
                                                        }
                
                                                        // toggle button function 
                                                        function toggle() {
                                                            console.log("Toggle Pay Btn");
                                                            var payBtn = document.getElementById("payButton");
                                                            payBtn.classList.toggle("disabled");
                                                        }
                                                        
                    </script>
                </body>
                
                </html>
                                    ';
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }
        $newResponse = $response->withHeader('Content-type', 'text/html');
        $body = $newResponse->getBody();
        $body->write($paymentPage);

        return $newResponse->withBody($body);
    }

    public function confirmPayment(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();
        $headers = $request->getHeaders();
        $postedHashString = $headers['Hashstring'][0];

        $responseData = array();

        try {
            /*

                {"id":"chg_TS070020212236s8MK2501232",
                    "object":"charge",
                    "live_mode":false,
                    "api_version":"V2",
                    "method":"POST",
                    "status":"CAPTURED",
                    "amount":6.05499999999999971578290569595992565155029296875,
                    "currency":"KWD",
                    "threeDSecure":false,
                    "card_threeDSecure":false,
                    "save_card":false,
                    "merchant_id":"",
                    "product":"",
                    "description":"\u0627\u0634\u062a\u0631\u0627\u0643 \u0628\u0645\u0648\u0642\u0639 \u0632\u0641\u0627\u0641",
                    "metadata":{"paymentToken":"eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE2MTE1OTk2MTQsImV4cCI6MTYxNDE5MTYxNCwidXNlcklkIjpudWxsLCJwYWNrYWdlSWQiOiIxIiwicGF5bWVudFZhbHVlIjoyMH0.IB9zN71PuHztngICZqa6XMaPBZxN7oardgosG5NtaExi2ESivnpv-GDIym5EDBN7FdxtZThqMdd0zEeVvcgA-_F8BVLmvufj9BVetMuuD7gN18pOAocugbM8qWv--ALdman4l9QxBgeOoqkSQvk0zsICVSrzdVy5dhfLIZrOc8Q"},
                    "transaction":{"authorization_id":"326622","timezone":"UTC+03:00","created":"1611614160700",
                        "expiry":{"period":30,"type":"MINUTE"},
                        "asynchronous":false,
                        "amount":7.5389999999999997015720509807579219341278076171875,
                        "currency":"BHD"},
                        "reference":{"track":"tck_TS050020212236s7QN2501950",
                            "payment":"0725212236019502890",
                            "gateway":"123456789",
                            "acquirer":"102519326622","transaction":"txn_0001","order":"ord_0001"},"response":{"code":"000","message":"Captured"},
                            "acquirer":{"response":{"code":"00","message":"Approved"}},
                            "card":{"object":"card","first_six":"511111","last_four":"1118"},"receipt":
                            {"id":"200025212236013310","email":false,"sms":true},
                            "customer":{"id":"cus_TS010020212236Yt862501997","first_name":"First Name","middle_name":"Middle Name","last_name":"Last Name","email":"demo@email.com","phone":{"country_code":"965","number":"99999999"}},
                            "source":{"object":"token","type":"CARD_NOT_PRESENT","payment_type":"DEBIT","payment_method":"MASTERCARD","channel":"INTERNET","id":"tok_CcTOc1311012cPZs527024"},
                            "redirect":{"status":"PENDING","url":"https:\/\/zefaaf.net\/api\/thankyou.html?token=600f1bee6ad8af00581c834d&mode=popup"},
                            "post":{"attempt":1,"status":"PENDING","url":"https:\/\/zefaaf.net\/api\/v1\/mobile\/confirmPayment"}}
                        
                */
            $paymentToken = $inputs['metadata']['paymentToken'];
            $decodedPayment = JWT::decode($paymentToken, $this->publicKey, array('RS256'));

            $decodedPayment = json_decode(json_encode($decodedPayment), true);

            $id = $inputs['id'];
            $amount = $inputs['amount'];
            $currency = $inputs['currency'];
            $gateway_reference = $inputs['reference']['gateway']; // 'charge.reference.gateway or authorize.reference.gateway'
            $payment_reference = $inputs['reference']['payment']; //'charge.reference.payment or authorize.reference.payment'
            $status = $inputs['status']; //     "status":"CAPTURED",  'charge.status or authorize.status or invoice.status'
            $created = $inputs['transaction']['created']; //'charge.transaction.created or authorize.transaction.created or invoice.created'
            $status = $inputs['status'];
            $SecretAPIKey = "sk_test_tCkcKQZ7DVmlyEu1g2wiNSzG";

            $toBeHashedString = 'x_id' . $id . 'x_amount' . $amount . 'x_currency' . $currency . 'x_gateway_reference' . $gateway_reference . 'x_payment_reference' . $payment_reference . 'x_status' . $status . 'x_created' . $created . '';


            $myHashString = hash_hmac('sha256', $toBeHashedString, $SecretAPIKey);

            $purchaseData = array();
            $purchaseData['userId'] = $decodedPayment['userId'];
            $purchaseData['paymentValue'] = $decodedPayment['paymentValue'];
            $purchaseData['packageId'] = $decodedPayment['packageId'];
            $purchaseData['paymentRefrence'] = $id;

            $message = implode('::', $purchaseData);
            // $message = "myHashString:: $myHashString ||| postedHashString :: $postedHashString || status:: $status";

            if ($myHashString == $postedHashString && $status == 'CAPTURED') //success payment
            {
                $this->purchasePackage($request,  $response, $purchaseData, 1);
                $services = new Services($this->container);
                $services->sendEmail('mash@dreamsoft-it.com', 'Success Payment', $message);
            } else {
                $services = new Services($this->container);
                $services->sendEmail('mash@dreamsoft-it.com', 'Invalid Payment', $message);
            }
        } catch (\PDOException $e) {

            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }




        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    function generateHash($hashSecret, $postData)
    {
        ksort($postData);
        $message = "";
        $appendAmp = 0;
        foreach ($postData as $key => $value) {
            if (strlen($value) > 0) {
                if ($appendAmp == 0) {
                    $message .= $key . '=' . $value;
                    $appendAmp = 1;
                } else {
                    $message .= '&' . $key . "=" . $value;
                }
            }
        }
        //echo $message;
        $secret = pack('H*', $hashSecret);
        return hash_hmac('sha256', $message, $secret);
    }

    public function vapPay(Request $request, Response $response, $args, $internal = 0)
    {

        $inputs = array();
        $inputs = $request->getParsedBody();
        $responseData = array();
        $sessionId = $inputs['sessionId'];
        $paymentRefrence = $inputs['paymentRefrence'];
        $paymentRefrenceArray = explode(":", $paymentRefrence);
        $userId = $paymentRefrenceArray[0];
        $packageId = $paymentRefrenceArray[1];
        //print_r($paymentRefrenceArray);
        //----Get user data---
        $sql[0] = " select name,email ,mobile from users where id=$userId";
        $sth = $this->db->prepare($sql[0]);
        $sth->execute();
        $data = $sth->fetchAll();
        $mobile = $data[0]['mobile'];
        $email = trim($data[0]['email']);
        $name = $data[0]['name'];

        $sql = "select usdValue from packages where id=$packageId";
        $sth = $this->db->prepare($sql);
        $sth->execute();
        $data = $sth->fetchAll();
        $paymentValue = $data[0]['usdValue'];
        $paymentRefrence .= ":$paymentValue";

        $postData = array(
            'sessionId'    => "$sessionId",
            'mobileNumber'    => "$mobile",
            'email'    => "payment@zefaaf.net",
            'amount'    => "$paymentValue",
            'firstName'    => "Zefaaf",
            'lastName'    => "User",
            'onAccept'    => 'https://zefaaf.net?success=true',
            'onFail'    => 'https://zefaaf.net/failed/payment',
            'notificationCallbackUrl' => 'https://api.zefaaf.net/v1/mobile/confirmVapPayment',
            'merchantReferenceId'  => "$paymentRefrence"
        );

        $secureHash = 'c1dff9fe62323439653931392d343665';
        $postData['hashSecret'] = $this->generateHash($secureHash, $postData);

        $postData['appId'] = '80c799cf-95f4-4a27-a442-9e65d7a36e09';
        $postData['password'] = 'Zef-Vap-Pay_2021';

        //print_r($postData );

        $body = http_build_query($postData);
        //echo $body;
        $headers = array('Content-Type : application/json');

        // $url = 'https://api.vapulus.com:1338/app/session/pay';
        $url = 'https://api.vapulus.com/v1.0/third-party/app/session/pay';

        $ch = curl_init();

        // curl_setopt($ch, CURLOPT_URL,            $url );
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
        // curl_setopt($ch, CURLOPT_POST,           1 );
        // curl_setopt($ch, CURLOPT_POSTFIELDS,     $body ); 
        // curl_setopt($ch, CURLOPT_HTTPHEADER,     array('Content-Type: text/plain')); 

        // $result=curl_exec ($ch);
        // curl_close( $ch );
        // echo $result;



        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        // curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
        curl_setopt($ch, CURLOPT_HTTPHEADER,     array('application/json; charset=utf-8'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        //curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        $result = curl_exec($ch);
        curl_close($ch);
        // echo $result;



        $result = json_decode($result, true);
        if ($result['message'] == 'Success') {
            if ($result['data']['status'] == 'pending' && $result['data']['action'] == 'process 3Ds') {

                $responseData['htmlBodyContent'] = $result['data']['htmlBodyContent'];
                $randFile = rand(1000, 1000000) . ".html";
                $paymentUrl = "./paymentsTemp/" . $randFile;
                file_put_contents($paymentUrl, $result['data']['htmlBodyContent']);
                $paymentUrl = "https://api.zefaaf.net/paymentsTemp/" . $randFile;

                $responseData['action'] = 'pending';
                $responseData['paymentUrl'] = $paymentUrl;
                return JsonResponse::withJson($response, json_encode($responseData), 200);
            } else if ($result['data']['status'] == 'accepted') {
                $responseData['action'] = 'accepted';
                $responseData['result'] = $result;
                return JsonResponse::withJson($response, json_encode($responseData), 200);
            }
        } else {
            $responseData['action'] = 'Failed';
            $responseData['result'] = $result;
            return JsonResponse::withJson($response, json_encode($responseData), 200);
        }
    }

    public function confirmVapPayment(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        try {

            $message =  implode(",", $inputs);
            $responseData = array();

            $services = new Services($this->container);
            $services->sendEmail('mash@dreamsoft-it.com', 'Payment', $message);

            // $paymentToken = $inputs['paymentToken'];
            // $decodedPayment = JWT::decode($paymentToken, $this->publicKey, array('RS256'));
            // $decodedPayment = json_decode(json_encode($decodedPayment),true);
            $paymentRefrence = $inputs['merchantReferenceId'];
            $paymentRefrenceArray = explode(":", $paymentRefrence);
            $userId = $paymentRefrenceArray[0];
            $packageId = $paymentRefrenceArray[1];
            $paymentValue = $paymentRefrenceArray[2];


            $purchaseData = array();
            $purchaseData['userId'] = $userId;
            $purchaseData['paymentValue'] = $paymentValue;
            $purchaseData['packageId'] = $packageId;
            $purchaseData['paymentRefrence'] = "vapulus";

            $message =  http_build_query($purchaseData, '', ', ');

            // notificationType=transaction, 
            // appId=80c799cf-95f4-4a27-a442-9e65d7a36e09,
            //  transactionId=b1cca611-43da-423d-ab75-735afff3826e, 
            //  merchantReferenceId=483%3A3%3A20, 
            //  amount=20, 
            //  currency=USD, 
            //  status=accepted, 
            //  createdAt=2021-03-23T13%3A56%3A52.000Z, 
            //  hashSecret=6a1fd512b0d13f23682bb406c46f802956008b58cf732a3211728898f97d8bfc



            if ($inputs['status'] == 'accepted') //success payment
            {
                $this->purchasePackage($request,  $response, $purchaseData, 1);
                $services = new Services($this->container);
                $services->sendEmail('support@zefaaf.net', 'Success Payment', $message);
            } else {
                $services = new Services($this->container);
                $services->sendEmail('support@zefaaf.net', 'Invalid Payment', $message);
            }
        } catch (\PDOException $e) {

            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }




        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function confirmPaypalPayment(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        try {

            //$message =  implode(",", $inputs);
            $message = json_encode($inputs);
            $responseData = array();

            $services = new Services($this->container);
            // $services->sendEmail('mash@dreamsoft-it.com','Zefaaf paypal Payment',$message);
            if ($inputs['event_type'] == 'PAYMENT.SALE.COMPLETED') {

                $custom = explode(":", $inputs['resource']['custom']);
                $userId = $custom[0];
                $packageId = $custom[1];

                $purchaseData = array();
                $purchaseData['userId'] = $userId;
                $purchaseData['paymentValue'] = $inputs['resource']['amount']['total'];
                $purchaseData['packageId'] = $packageId;
                $purchaseData['paymentRefrence'] = 'PayPal';
                list($usec, $sec) = explode(" ", microtime());
                $purchaseData['transactionId'] = mt_rand(100000000, 999999999) . substr($usec, 5);


                $message =  http_build_query($purchaseData, '', ', ');
                $services = new Services($this->container);
                $services->sendEmail('mash@dreamsoft-it.com', 'Paypal Success Payment', $message);

                $this->purchasePackage($request,  $response, $purchaseData, 1);
            }
        } catch (\PDOException $e) {

            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function updateMySearchSetings(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");

        $residentCountryId =  isset($inputs['residentCountryId']) ? $inputs['residentCountryId'] : -1;
        $nationalityCountryId =  isset($inputs['nationalityCountryId']) ? $inputs['nationalityCountryId'] : -1;
        $weightFrom =  isset($inputs['weightFrom']) ? $inputs['weightFrom'] : -1;
        $weightTo =  isset($inputs['weightTo']) ? $inputs['weightTo'] : -1;
        $heightFrom =  isset($inputs['heightFrom']) ? $inputs['heightFrom'] : -1;
        $heightTo =  isset($inputs['heightTo']) ? $inputs['heightTo'] : -1;
        $mariageStatues =  isset($inputs['mariageStatues']) ? $inputs['mariageStatues'] : -1;
        $mariageKind =  isset($inputs['mariageKind']) ? $inputs['mariageKind'] : -1;
        $skinColor =  isset($inputs['skinColor']) ? $inputs['skinColor'] : -1;
        $smoking =  isset($inputs['smoking']) ? $inputs['smoking'] : -1;
        $prayer =  isset($inputs['prayer']) ? $inputs['prayer'] : -1;
        $education =  isset($inputs['education']) ? $inputs['education'] : -1;
        $financial =  isset($inputs['financial']) ? $inputs['financial'] : -1;
        $workField =  isset($inputs['workField']) ? $inputs['workField'] : -1;
        $income =  isset($inputs['income']) ? $inputs['income'] : -1;
        $helath =  isset($inputs['helath']) ? $inputs['helath'] : -1;
        $ageFrom =  isset($inputs['ageFrom']) ? $inputs['ageFrom'] : -1;
        $ageTo =  isset($inputs['ageTo']) ? $inputs['ageTo'] : -1;
        $veil =  isset($inputs['veil']) ? $inputs['veil'] : -1;


        $sql = "replace into automatedSearch (userId,residentCountryId,nationalityCountryId,weightFrom,weightTo,
                heightFrom,heightTo,mariageStatues,mariageKind,skinColor,
                smoking,prayer,education,financial,workField,income,helath,ageFrom,ageTo,veil) 
                values ($userId,'$residentCountryId','$nationalityCountryId',$weightFrom,$weightTo,
                $heightFrom,$heightTo,'$mariageStatues','$mariageKind','$skinColor',
                '$smoking','$prayer','$education','$financial','$workField','$income','$helath',$ageFrom,$ageTo,'$veil')";

        try {
            //  echo $sql;
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function getMySearchSettings(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");

        $sql = "select * from  automatedSearch where userId=$userId";

        try {
            //  echo $sql;
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $searchSettings = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
            if (count($searchSettings) != 1) {
                $data['residentCountryId'] = -1;
                $data['nationalityCountryId'] = -1;
                $data['weightFrom'] = 40;
                $data['weightTo'] = 200;
                $data['heightFrom'] = 130;
                $data['heightTo'] = 230;
                $data['ageFrom'] = 18;
                $data['ageTo'] = 65;
                $data['mariageStatues'] = -1;
                $data['mariageKind'] = -1;
                $data['skinColor'] = -1;
                $data['smoking'] = -1;
                $data['prayer'] = -1;
                $data['education'] = -1;
                $data['financial'] = -1;
                $data['workField'] = -1;
                $data['income'] = -1;
                $data['helath'] = -1;
                $data['veil'] = -1;


                $searchSettings[0] = $data;
            }
            $responseData['data'] = $searchSettings;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function getMySearch(Request $request, Response $response, $args, $internal = 0)
    {
        $pageSize =  $this->pageSize;
        $page = isset($args['page']) ? $args['page'] : 0;
        $page *= $pageSize;
        $orderBy = isset($args['orderBy']) ? $args['orderBy'] : 'id';

        $userId = $request->getAttribute("userId");
        $userGender = $request->getAttribute("userGender");

        $sql = "select * from automatedSearch where userId=$userId ";

        $responseData = array();

        try {

            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();
            if (count($data) > 0) {
                $data[0]['orderBy'] = $orderBy;
                $data[0]['page'] = $page;

                $data[0]['userGender'] = $userGender;

                $responseData = $this->search($request, $response, $data[0], 2);
                $responseData['settingsExist'] = 1;
            } else {
                $responseData['status'] = 'success';
                $responseData['rowsCount'] = 0;
                $responseData['data'] = [];
                $responseData['settingsExist'] = 0;
            }
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }


    public function uploadSoundFile(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $userId = $request->getAttribute("userId");


        try {
            $args['folderName'] = 'chatResources/userVoices/';
            $services = new Services($this->container);
            $fileUploaded = $services->upload($request, $response, $args, true);
            $filePath = "/" . $args['folderName'] . $fileUploaded['fileName'];
            $fileSize =  filesize($args['folderName'] . $fileUploaded['fileName']);
            // $responseData['status'] = 'success'; 
            // $responseData['filePath'] = $filePath;
            //if($fileSize>0){
            $responseData['status'] = 'success';
            $responseData['filePath'] = $filePath;
            // }
            // else{
            //     $responseData['status'] = 'error'; 
            //     $responseData['errorCode'] = 'file size is empty or too small';


            // }

        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function updateBlogViews(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();
        $blogId = $inputs['blogId'];
        $sql = "update blog set views = views+1 where id = $blogId";

        try {
            // echo $sql;
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }


    public function getAgents(Request $request, Response $response, $args, $internal = 0)
    {

        $sql = "select agents.name,agents.email,agents.mobile,whats,countries.nameAr as countryName ,localValue 
                from agents inner join 
                countries on agents.countryId = countries.id where agents.active=1";
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = count($data);
            $responseData['data'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function addAgent(Request $request, Response $response, $args, $internal = 0)
    {

        $inputs = array();
        $inputs = $request->getParsedBody();
        $responseData = array();

        $name = $inputs['name'];
        $email = $inputs['email'];
        $countryId =  $inputs['countryId'];
        $mobile =  $inputs['mobile'];
        $whats =  $inputs['whats'];

        $imageFile =  $inputs['imageFile'];
        $paypalAccount =  $inputs['paypalAccount'];

        $uploadedFiles = $request->getUploadedFiles();
        if (!empty($uploadedFiles['attachment'])) {
            $services = new Services($this->container);
            $fileUploaded = $services->upload($request, $response, $args, true);
            $imageFile = $fileUploaded['fileName'];
        }


        try {
            $sql = " select mobile from agents where mobile='$mobile' or email='$email'";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();
            if (count($data) > 0) { //Duplicate email or mobile
                $responseData['status'] = 'error';
                $responseData['rowsCount'] = 0;
                $responseData['errorCode'] = '1';
                $responseData['errorMessage'] = 'Duplicate email or mobile';
            } else {
                $sql = "insert into agents  (name,email,password,countryId,mobile,whats,nationalId,paypalAccount,active) 
                values('$name','$email','$password',$countryId,'$mobile','$whats','$imageFile','$paypalAccount',0)";

                $sth = $this->db->prepare($sql);
                $sth->execute();
                $responseData['status'] = 'success';
            }
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }


    public function marriageRequest(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $realName =  $inputs['realName'];
        $whats =  trim($inputs['whats'], '+');
        $age =  $inputs['age'];
        $mariageStatues =  $inputs['mariageStatues'];
        $mariageKind =  ($mariageStatues == '1') ? 5 : $inputs['mariageKind'];
        $residentCountryId =  $inputs['residentCountryId'];
        $aboutMe =  $inputs['aboutMe'];
        $aboutOther =  $inputs['aboutOther'];
        $thanksMessage =  $inputs['thanksMessage'];

        $responseData = array();
        if (
            is_null($realName) || $realName == '' || is_null($whats) || strlen($whats) < 8
            || is_null($age) || $age == '' || $mariageStatues == '' || 
            //is_null($residentCountryId) || $residentCountryId == '' || 
             $mariageKind == '' ||
            is_null($aboutMe) || $aboutMe == '' || is_null($aboutOther) || $aboutOther == '' ||
            is_null($thanksMessage) || $thanksMessage == ''
        ) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  'missing data';
            return JsonResponse::withJson($response, json_encode($responseData), 400);
        }
        if ($residentCountryId == null) {
            $residentCountryId = 0;
        }
        try {

            $sql = "select id,title from fixedData where active=1 order by id";

            $sth = $this->db->prepare($sql);
            $sth->execute();
            $fixedData = $sth->fetchAll();



            $fullMessage = "إسمي: ";
            $fullMessage .= $realName;

            $fullMessage .= "\n";
            $fullMessage .= "\n";

            $fullMessage .= "عمري: ";
            $fullMessage .= $age;

            $fullMessage .= "\n";
            $fullMessage .= "\n";

            $fullMessage .= "حالتي الإجتماعية: ";
            $fullMessage .= $fixedData[array_search($mariageStatues, array_column($fixedData, 'id'))]['title'];

            $fullMessage .= "\n";
            $fullMessage .= "\n";

            $fullMessage .= "نوع الزواج: ";
            $fullMessage .= $fixedData[array_search($mariageKind, array_column($fixedData, 'id'))]['title'];

            $fullMessage .= "\n";
            $fullMessage .= "\n";


            $fullMessage .= " رقم الواتس: ";
            $fullMessage .= $whats;

            $fullMessage .= "\n";
            $fullMessage .= "\n";


            $fullMessage .= "بعض من مواصفاتي: ";
            $fullMessage .= $aboutMe;

            $fullMessage .= "\n";
            $fullMessage .= "\n";

            $fullMessage .= "مواصفات شريكة (شريك) حياتي: ";
            $fullMessage .= $aboutOther;

            $fullMessage .= "\n";
            $fullMessage .= "\n";

            $fullMessage .= "كلمة شكر لفريق العمل:";
            $fullMessage .= $thanksMessage;

            $fullMessage .= "\n";
        
          
            $sql = "insert into messages(userId,reasonId,title,message,whats,realName,residentCountryId) 
                values($userId,4,'طلب زواج','$fullMessage',$whats,'$realName', $residentCountryId )";
            //echo $sql;
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['id'] = $this->db->lastInsertId();
            $services = new Services($this->container);
            //$services->sendEmail('mash@dreamsoft-it.com','طلب زواج',$fullMessage);

        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }


    public function requestMobile(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $otherId =  $inputs['otherId'];

        $ignored = $this->checkIgnored($userId, $otherId);
        if ($ignored == 1) {
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  'ignore list';
            return JsonResponse::withJson($response, json_encode($responseData), 200);
        }

        $sql = "REPLACE into lists (userId,otherId,listType) values($userId,$otherId,7)";
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            //---Send Notification----
            $args['otherId'] = $otherId;
            $args['notiType'] = 7;
            $this->AddNotification($request, $response, $args);
            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function cancelRequestMobile(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $otherId =  $inputs['otherId'];


        $sql = "delete from lists where userId =$userId  and otherId = $otherId and listType =7";
        $sql1 = "delete from lists where userId = $otherId  and otherId = $userId and listType =8";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function replyRequestMobile(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $otherId =  $inputs['otherId'];
        $statues =  $inputs['statues']; //4 approved 5 refused

        try {
            //  $sql = "REPLACE into notifications (userId,otherId,notiType) values($userId,$otherId,$statues)";
            if ($statues == 4) { //Approved
                $args['listType'] = '8';
                $this->addToMyFavorites($request, $response, $args, 1);
                //---Send Notification----
                $args['otherId'] = $otherId;
                $args['notiType'] = 8;
                $this->AddNotification($request, $response, $args);
            } else { //declined-removed
                $args['listType'] = '8';
                $this->removeFromFavorites($request, $response, $args, 1);
                //---Send Notification----
                $args['otherId'] = $otherId;
                $args['notiType'] = 9;
                $this->AddNotification($request, $response, $args);
            }
            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        if ($internal)
            return $responseData;
        else
            return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
}//----Class end
