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


final class Crud
{
    private const API_NAME = 'Mash-APi';

    private const API_VERSION = '1.0.0';

    /** @var Container */
    private $container;

    private $db;
    private $pathToUpload = "uploadFolder";
    private $imgSmallWidth = 300;
    private $imgMediumWidth = 600;
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


    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->db = $container->get('db');
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

    private function generateToken(int $userId, int $type)
    {
        $now = new DateTime("Now");
        $future = new DateTime("now +30 days");
        $payload = [
            "iat" => $now->getTimeStamp(),
            "exp" => $future->getTimeStamp(),
            "userId" => "$userId",
            "type" => "$type",
        ];
        $token = JWT::encode($payload, $this->privateKey, "RS256");

        // $token = JWT::encode($payload, 'Secnkj9ne8nkmndsa8enk3usnfw0nlsAS', "HS256");
        return $token;
    }

    public function login(Request $request, Response $response, $args, $internal = 0)
    {

        $responseData = array();

        $inputs = array();
        // var_dump($request);
        $inputs = $request->getParsedBody();

        $email = $inputs['email'];
        $password =  md5($inputs['password']);

        $sql = array();
        $sql[0] = "select id,name,email,type,active from admins where email='$email' and password='$password'";
        $sql[1] = "update admins set lastAccess=CURRENT_TIMESTAMP() 
                     where id=:userId";
        $sql[2] = "select moduleId,moduleRead,moduleWrite,moduleDelete from adminsPrivileges where adminId=:id";

        $responseData = array();
        try {
            $sth = $this->db->prepare($sql[0]);
            $sth->execute();
            $data = $sth->fetchAll();

            if (sizeof($data) > 0) //user exists
            {
                $responseData['status'] = 'success';
                $responseData['token'] = $this->generateToken($data[0]['id'], $data[0]['type']);
                $responseData['pathToUpload'] = $this->pathToUpload;
                $responseData['data'] = $data;
                //---Update user data --
                $sth = $this->db->prepare($sql[1]);
                $sth->bindParam(':userId', $data[0]['id']);
                $sth->execute();
                //---Select Privlges --
                $sth = $this->db->prepare($sql[2]);
                $sth->bindParam(':id', $data[0]['id']);
                $sth->execute();
                $data = $sth->fetchAll();
                $responseData['privlages'] = $data;
            } else {
                $responseData['status'] = 'error';
                $responseData['rowsCount'] = 0;
                $responseData['errorMessage'] = 'Admin not exists ';
            }
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function updateMyPassword(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();

        $userId = $request->getAttribute("userId");
        $password =  md5($inputs['password']);

        $sql = " update admins set password='$password' where id=$userId";
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
    public function getAdminUser(Request $request, Response $response, $args, $internal = 0)
    {

        $id = $args['id'];

        $sql = array();
        $sql[0] = "select id,name,email,type,active from admins where id=$id";
        $sql[2] = "select moduleId,moduleRead,moduleWrite,moduleDelete from adminsPrivileges where adminId=$id";

        $responseData = array();
        try {
            $sth = $this->db->prepare($sql[0]);
            $sth->execute();
            $data = $sth->fetchAll();

            if (sizeof($data) > 0) //user exists
            {
                $responseData['status'] = 'success';
                $responseData['data'] = $data;
                //---Select Privlges --
                $sth = $this->db->prepare($sql[2]);
                $sth->execute();
                $data = $sth->fetchAll();
                $responseData['privlages'] = $data;
            } else {
                $responseData['status'] = 'error';
                $responseData['rowsCount'] = 0;
                $responseData['errorMessage'] = 'Admin not exists ';
            }
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function addAdminUser(Request $request, Response $response, $args, $internal = 0)
    {

        $inputs = array();
        $inputs = $request->getParsedBody();
        $responseData = array();

        $name = $inputs['name'];
        $email = $inputs['email'];
        $password =  $inputs['password'];
        // $password =  md5($inputs['password']);

        $type = $inputs['type'];
        $active = $inputs['active'];
        $privileges = $inputs['privileges']; //[{"moduleId": 1,"read": 1,"write": 1,"delete": 1}]

        $sql = array();
        $sql[0] = "insert into admins  (name,email,password,type,active) 
                values('$name','$email','$password',$type,$active)";

        $sql[1] = "insert into adminsPrivileges (adminId,moduleId,moduleRead,moduleWrite,moduleDelete) values ";

        try {
            $sth = $this->db->prepare($sql[0]);
            $sth->execute();
            $adminId = $this->db->lastInsertId();

            $privileges = json_decode($privileges, true);

            $first = 0;
            foreach ($privileges as $module) {
                $moduleId = $module['moduleId'];
                $read = $module['read'];
                $write = $module['write'];
                $delete = $module['delete'];
                if ($first == 1)
                    $sql[1] .= ",";
                else
                    $first = 1;
                $sql[1] .= "($adminId,$moduleId,$read,$write,$delete)";
            }



            $sth = $this->db->prepare($sql[1]);
            // echo $sql[1];

            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['id'] = $adminId;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function updateAdminUser(Request $request, Response $response, $args, $internal = 0)
    {

        $inputs = array();
        $inputs = $request->getParsedBody();
        $responseData = array();

        $adminId = $inputs['id'];
        $name = $inputs['name'];
        $email = $inputs['email'];
        $password =  $inputs['password'];

        // $password =  md5($inputs['password']);
        $type = $inputs['type'];
        $active = $inputs['active'];
        $privileges = $inputs['privileges']; //[{"moduleId": 1,"read": 1,"write": 1,"delete": 1}]

        $sql = array();
        $sql[0] = "update admins set name ='$name' ,email = '$email',password = '$password',type = $type,active = $active
                    where id = $adminId";

        $sql[1] = "insert into adminsPrivileges (adminId,moduleId,moduleRead,moduleWrite,moduleDelete) values ";
        $sql[2] = "delete from adminsPrivileges where adminId = $adminId";

        try {
            $sth = $this->db->prepare($sql[2]);
            $sth->execute();

            $privileges = json_decode($privileges, true);

            $first = 0;
            foreach ($privileges as $module) {
                $moduleId = $module['moduleId'];
                $read = $module['read'];
                $write = $module['write'];
                $delete = $module['delete'];
                if ($first == 1)
                    $sql[1] .= ",";
                else
                    $first = 1;
                $sql[1] .= "($adminId,$moduleId,$read,$write,$delete)";
            }



            $sth = $this->db->prepare($sql[1]);
            $sth->execute();

            $responseData['status'] = 'success';
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function get(Request $request, Response $response, $args, $internal = 0)
    {

        $pageSize = $this->pageSize;
        $page = $args['page'] * $pageSize;
        $table = $args['table'];
        $order = isset($args['order']) ? $args['order'] : 'desc';

        $sql = " select * from $table ";
        $sql .= ($table == 'countries') ? " order by nameAr asc" : " order by id $order  ";
        if (isset($args['page']) and $args['page'] != -1)
            $sql .= " limit $page,$pageSize ";
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

    public function getById(Request $request, Response $response, $args, $internal = 0)
    {

        $pageSize = $this->pageSize;
        $id = $args['id'];
        $table = $args['table'];

        $sql = " select * from $table where id=$id ";
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

    public function getFreeSql(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();

        $inputs = array();
        $inputs = $request->getParsedBody();
        $sql = $inputs['sql'];


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
    public function getByField(Request $request, Response $response, $args, $internal = 0)
    {

        $pageSize = $this->pageSize;
        $table = $args['table'];
        $field = $args['field'];
        $value = $args['value'];

        $page = $args['page'] * $pageSize;
        $order = isset($args['order']) ? $args['order'] : 'desc';


        $sql = " select * from $table where $field=$value order by id desc limit $page,$pageSize ";
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

    public function getByLikeField(Request $request, Response $response, $args, $internal = 0)
    {

        $pageSize = $this->pageSize;
        $table = $args['table'];
        $field = $args['field'];
        $value = $args['value'];

        $sql = " select * from $table where $field like '%$value%' order by id desc  ";
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

    public function add(Request $request, Response $response, $args)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();
        $responseData = array();

        $table = $args['table'];
        $unique = $inputs['unique'];
        $fields = $inputs['fields'];
        $fNames = "";
        $fValues = "";

        $fieldsArray = json_decode($fields);
        foreach ($fieldsArray as $f => $v) {

            $fNames .= ($fNames != "") ? ",$f" : $f;
            $fValues .= ($fValues != "") ? ",'$v'" : "'$v'";
        }
        $sql = "insert into $table ($fNames) values($fValues)";


        if ($unique) {
            $uniqueKeys = explode(",", $unique);
            $uniqueResult = $this->unique($table, $uniqueKeys, $fieldsArray, 0);
            if ($uniqueResult['duplicateData'])
                return JsonResponse::withJson($response, json_encode($uniqueResult), 200);
        }
        try {
            //  echo $sql;
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $responseData['error'] = false;
            $responseData['id'] = $this->db->lastInsertId();
        } catch (\PDOException $e) {
            $responseData['error'] = true;
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function unique($table, $fields, $values, $escapeId)
    {
        $duplicateData = array();
        //$duplicateData['duplicateData']=array();
        foreach ($fields as $field) {
            $value = $values->{$field};
            $sql = "select $field from $table where id !=$escapeId and ";
            $sql .= "$field='$value'";
            // echo $sql;

            $sth = $this->db->prepare($sql);
            try {
                $sth->execute();
                $data = $sth->fetchAll();
                if ($data) {
                    array_push($duplicateData, $field);
                }
            } catch (\PDOException $e) {
                $responseData = array();
                $responseData['error'] = true;
                $responseData['errorCode'] =  $e->getMessage();
            }
        }
        if (!$responseData['errorCode']) {
            $responseData = array();
            $responseData['error'] = false;
            $responseData['duplicateData'] = $duplicateData;
        }
        return $responseData;
    }

    public function update(Request $request, Response $response, $args)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();
        $responseData = array();

        $table = $args['table'];
        $id = $args['id'];

        $unique = $inputs['unique'];
        $fields = $inputs['fields'];
        $fNames = "";
        $fValues = "";

        $fieldsArray = json_decode($fields);
        foreach ($fieldsArray as $f => $v) {

            $fNames .= ($fNames != "") ? ",$f='$v'" : "$f='$v'";
        }
        $sql = "update $table set $fNames where id=$id";
        // echo $sql;

        if ($unique) {
            $uniqueKeys = explode(",", $unique);
            $uniqueResult = $this->unique($table, $uniqueKeys, $fieldsArray, $id);
            if ($uniqueResult['duplicateData'])
                return JsonResponse::withJson($response, json_encode($uniqueResult), 200);
        }
        try {
            // echo $sql;
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $responseData['error'] = false;
        } catch (\PDOException $e) {
            $responseData['error'] = true;
            $responseData['errorCode'] =  $e->getMessage();
            $responseData['sql'] = $sql;
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function delete(Request $request, Response $response, $args, $internal = 0)
    {

        $id = $args['id'];
        $table = $args['table'];
        if ($table == 'packages')
            $sql = "update $table set active=2 where id=$id ";
        else
            $sql = " delete  from $table where id=$id ";
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

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function uploadFile(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $responseData = array();

        try {
            $services = new Services($this->container);

            $fileUploaded = $services->upload($request, $response, array(), true);
            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
            $responseData['fileName'] = $fileUploaded['fileName'];
            $responseData['filePath'] = $fileUploaded['filePath'] . "/medium";
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

    public function getHome(Request $request, Response $response, $args, $internal = 0)
    {


        $sql1 = " select sum(if(Day(creationDate)=Day(CURDATE()),1,0 )) as todayUsers,
                    sum(if(Month(creationDate)=Month(CURDATE()),1,0 )) as monthUsers 
                    from users 
                    where YEAR(creationDate) = Year(CURDATE()) and Month(creationDate) = Month(CURDATE())";

        $sql2 = " select sum(if(gender=0,1,0)) as men,sum(if(gender=1,1,0)) as women
                    ,sum(if(mobileType=1,1,0)) as android,sum(if(mobileType=2,1,0)) as iphone,count(id) as totalUsers 
                    from users where active =1";

        $sql3 = " select 
                    sum(if(Day(purchaseDateTime)=Day(CURDATE()) and agentId=0 and paymentValue!=0,1,0 )) as todayPurchase,
                    sum(if(Day(purchaseDateTime)=Day(CURDATE()) and agentId=0 and paymentValue!=0,paymentValue,0 )) as todayPurchaseValue,
                    sum(if(Month(purchaseDateTime)=Month(CURDATE()) and agentId=0 and paymentValue!=0,1,0 )) as monthPurchase ,
                    sum(if(Month(purchaseDateTime)=Month(CURDATE()) and agentId=0 and paymentValue!=0,paymentValue,0 )) as monthPurchaseValue,
                    sum(if(Month(purchaseDateTime)=Month(CURDATE()) and agentId=0 and paymentValue=0,1,0 )) as freeMonthPurchase ,
                    sum(if(Month(purchaseDateTime)=Month(CURDATE()) and agentId=0 and paymentValue=0,paymentValue,0 )) as freeMonthPurchaseValue,

                    sum(if(Day(purchaseDateTime)=Day(CURDATE())  and paymentValue!=0 and mobileType=1,1,0 )) as todayAndroidPurchase,
                    sum(if(Day(purchaseDateTime)=Day(CURDATE())  and paymentValue!=0 and mobileType=2,1,0 )) as todayIphonePurchase,
                    sum(if(Month(purchaseDateTime)=Month(CURDATE())  and paymentValue!=0 and mobileType=1,1,0 )) as monthAndroidPurchase ,
                    sum(if(Month(purchaseDateTime)=Month(CURDATE())  and paymentValue!=0 and mobileType=2,1,0 )) as monthIphonePurchase ,
                    sum(if(Month(purchaseDateTime)=Month(CURDATE())  and paymentValue=0 and mobileType=1,1,0 )) as freeMonthAndroidPurchase ,
                    sum(if(Month(purchaseDateTime)=Month(CURDATE())  and paymentValue=0 and mobileType=2,1,0 )) as freeMonthIphonePurchase, 

                    (select sum(if( paymentValue!=0,1,0 )) as totalPurchase  from purchases inner join users on purchases.userId = users.id ) as totalPurchase,
					(select sum(if( paymentValue!=0,paymentValue,0 )) as totalPurchaseValue  from purchases inner join users on purchases.userId = users.id ) as totalPurchaseValue,
					
					(select sum(if( paymentValue!=0 and mobileType=1,1,0 ))  from purchases inner join users on purchases.userId = users.id ) as totalAndroidPurchase, 
					(select sum(if( paymentValue!=0 and mobileType=2,1,0 ))   from purchases inner join users on purchases.userId = users.id ) as totalIphonePurchase 

                    from purchases inner join users on purchases.userId = users.id
                                        where YEAR(purchaseDateTime) = Year(CURDATE()) and Month(purchaseDateTime) = Month(CURDATE())";

        $sql4 = " select count(id) as newMessages from messages where owner=0 and readed=0 ";

        $sql5 = "select paymentsMethoda.paymentRefrence,pur.total 
        from paymentsMethoda left join
        (select paymentRefrence,count(id) as total 
        from  purchases 
            where YEAR(purchaseDateTime) = Year(CURDATE()) and Month(purchaseDateTime) = Month(CURDATE())
                            group by purchases.paymentRefrence ) as pur
        on paymentsMethoda.paymentRefrence = pur.paymentRefrence";


        $responseData = array();

        try {
            $responseData['status'] = 'success';
            //                $responseData['rowsCount'] = count(1);
            $responseData['rowsCount'] = 1;

            $sth = $this->db->prepare($sql1);
            $sth->execute();
            $data = $sth->fetchAll();
            $responseData['data']['users'] = $data[0];

            $sth = $this->db->prepare($sql2);
            $sth->execute();
            $data = $sth->fetchAll();
            $responseData['data']['users']['waitingUsers'] = 0; //$data[0]['waitingUsers']; 
            $responseData['data']['users']['men'] = $data[0]['men'];
            $responseData['data']['users']['women'] = $data[0]['women'];
            $responseData['data']['users']['android'] = $data[0]['android'];
            $responseData['data']['users']['iphone'] = $data[0]['iphone'];
            $responseData['data']['users']['totalUsers'] = $data[0]['totalUsers'];


            $sth = $this->db->prepare($sql3);
            $sth->execute();
            $data = $sth->fetchAll();
            $responseData['data']['purchases'] = $data[0];

            $sth = $this->db->prepare($sql4);
            $sth->execute();
            $data = $sth->fetchAll();
            $responseData['data']['messages'] = $data[0];

            $sth = $this->db->prepare($sql5);
            $sth->execute();
            $data = $sth->fetchAll();
            $responseData['data']['paymentRefrences'] = $data;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function getUsers(Request $request, Response $response, $args, $internal = 0)
    {

        $responseData = array();
        $search = $args['search'];


        // $sql = " select users.id,userName,age,gender,available,lastAccess,packageId,residentCountryId,
        //             countries1.nameAr as residentCountryName,nationalityCountryId,countries2.nameAr as nationalityCountryName,
        //             cityId,cities.nameAr as cityName,lastAccess,mariageStatues,users.active
        //             from users inner join countries as countries1 inner join countries as countries2
        //             inner join cities 
        //             on users.residentCountryId = countries1.id and users.nationalityCountryId = countries2.id
        //             and users.cityId=cities.id ";
        // $sql.=isset($search)? " where userName like '%$search%' or mobile like '%$search%' or name like '%$search%'" : " ";            
        // $sql.=" order by id desc limit $page,$pageSize ";

        $sql = "select id,userName from users  ";
        $sql .= isset($search) ? " where userName like '%$search%' or mobile like '%$search%' or name like '%$search%'" : " ";
        $sql .= " order by id desc";

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

    public function reActiveUsers(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
    
        // Ensure the request is AJAX
        if (!$request->ajax()) {
            $responseData['status'] = 'error';
            $responseData['message'] = 'Invalid request type.';
            
            if ($internal) {
                return $responseData;
            } else {
                return JsonResponse::withJson($response, json_encode($responseData), 400);
            }
        }
    
        try {
            // Construct the SQL query to update users
            $sql = "UPDATE users SET active = 1 WHERE active = 3";
    
            // Execute the query
            $affectedRows = $this->db->prepare($sql);
    
            $sth->execute();
    
    
            // Prepare the success response
            $responseData['status'] = 'success';
            $responseData['message'] = "$affectedRows users have been activated.";
        } catch (\Exception $e) {
            // Rollback the transaction if something goes wrong
            DB::rollback();
    
            // Prepare the error response
            $responseData['status'] = 'error';
            $responseData['message'] = 'Failed to update users: ' . $e->getMessage();
        }
    
        if ($internal) {
            return $responseData;
        } else {
            return JsonResponse::withJson($response, json_encode($responseData), 200);
        }
    }


    public function getPackages(Request $request, Response $response, $args, $internal = 0)
    {
        $pageSize =  $this->pageSize;
        $countryId = $args['countryId'];
        $discounted = $args['discounted'];


        $sql = " select packages.*,countries.`nameAr` as countryName from 
                    packages inner join countries
                    on packages.countryId = countries.id
                    where packages.active!=2 ";

        $sql .= ($countryId >= 0) ? " and countryId=$countryId" : " ";
        $sql .= ($discounted >= 0) ? " and discounted=$discounted" : " ";
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

    public function getUserFavorites(Request $request, Response $response, $args, $internal = 0)
    {
        $pageSize =  $this->pageSize;
        $userId = $args['userId'];
        $listType = $args['listType'];

        $page = isset($args['page']) ? $args['page'] : 0;
        $page *= $pageSize;
        $search = $args['search'];

        $sql = " select lists.userId,lists.otherId,lists.listType,lists.listDateTime,userName,mobile,gender,
                available,lastAccess,packageId,
                age,countries1.nameAr as residentCountryName,nationalityCountryId,
                countries2.nameAr as nationalityCountryName,
                cityId,cities.nameAr as cityName,lastAccess,mariageStatues  from lists 
                inner join users inner join countries as countries1 inner join countries as countries2
                inner join cities 
                on users.residentCountryId = countries1.id and users.nationalityCountryId = countries2.id
                and users.cityId=cities.id  and lists.otherId = users.id
                where userId=$userId and listType=$listType";

        $sql .= isset($search) ? " and (userName like '%$search%' or mobile like '%$search%' )" : " ";
        $sql .= " order by lists.id desc  limit $page,$pageSize ";
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
    public function getPurchases(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $pageSize = $this->pageSize;
        $page = isset($args['page']) ? $args['page'] : 0;
        $page *= $pageSize;
        $search = isset($args['search']) ? urldecode($args['search']) : null;


        $sql = " select purchases.*,users.userName as name,users.gender,users.mobile,users.residentCountryId
                    ,packages.title , users.detectedCountry as nameAr 
                    from purchases inner join users inner join packages inner join countries 
                    on purchases.userId = users.id and purchases.packageId = packages.id and 
                    users.residentCountryId = countries.id where deleted = 0 ";
        $sql .= isset($search) ? " and (userName like '%$search%' or mobile like '%$search%' or users.id = '$search'
                                or countries.nameAr like '%$search%' or packages.title like '%$search%' or paymentRefrence like '%$search%')" : " ";

        $sql .= " order by purchases.id desc limit $page,$pageSize ";



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

    public function listMessages(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $pageSize = $this->pageSize;
        $page = isset($args['page']) ? $args['page'] : ' ';
        $page *= $pageSize;
        $search = isset($args['search']) ? $args['search'] : 0;

        $sql = " select messages.* ,users1.userName ,if(otherId=0,'',users2.userName) as complaintUser
                from messages inner join users as users1 
                inner join users as users2 on messages.userId = users1.id and  messages.otherId = users2.id
                where messages.active =1 ";

        $sql .= ($search != ' ') ? " and (users1.userName like '%$search%' or users2.userName like '%$search%' or messages.title like '%$search%' or messages.message like '%$search%')" : "";
        $sql .= " order by messages.id desc limit $page,$pageSize";
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

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }


    public function getMessageDetails(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $id = $args['id'];

        $sql = " select messages.* ,users1.userName ,if(otherId=0,'',users2.userName) as complaintUser
                from messages inner join users as users1 
                inner join users as users2 on messages.userId = users1.id and  messages.otherId = users2.id
                where messages.id = $id";



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
    public function listSuccessStories(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $pageSize = $this->pageSize;
        $page = isset($args['page']) ? $args['page'] : 0;
        $page *= $pageSize;

        $sql = " select successStories.*,users1.userName as husband,users2.userName as wife
                    from successStories inner join users as users1 
                    on successStories.husId = users1.id
                    inner join users as users2             
                    on successStories.wifId = users2.id
                    order by successStories.id desc limit $page,$pageSize";


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
    public function usersChart(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $from = $inputs['from'] ?? null;
        $to = $inputs['to'] ?? null;

        $sql = " select  residentCountryId,nameAr,
                    sum(if(gender=0 and packageId=0,1,0)) as FreeMens, sum(if(gender=0 and packageId>0,1,0)) as PremiumMens,
                    sum(if(gender=1 and packageId=0,1,0)) as FreeWomens, sum(if(gender=1 ,1,0)) as PremiumWomens,
                    count(users.id) as totalUsers
                    from users inner join countries on users.residentCountryId = countries.id";
        $sql .= (isset($from) && isset($to)) ? " where creationDate >='$from' and creationDate <='$to'" : " ";
        $sql .= " group by residentCountryId order by count(users.id) desc";


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

    public function paymentsChart(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();

        $from = $inputs['from'] ?? null;
        $to = $inputs['to'] ?? null;

        $sql = " select users.residentCountryId,nameAr,count(purchases.id) as purchasesCount,
                sum(paymentValue) as payments 
                from purchases inner join users inner join countries 
                on purchases.userId = users.id 
                and  users.residentCountryId = countries.id 
                where paymentValue>0 ";
        $sql .= (isset($from) && isset($to)) ? " and purchaseDateTime >='$from' and purchaseDateTime <='$to'" : " ";

        $sql .= " group by users.residentCountryId order by payments desc ";

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

    public function listUsers(Request $request, Response $response, $args, $internal = 0)
    {

        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();
        $pageSize = isset($inputs['pageSize']) ? $inputs['pageSize'] : $this->pageSize;
        $page = isset($inputs['page']) ? $inputs['page'] : 0;
        $page *= $pageSize;

        $orderBy = $inputs['orderBy'] ?? null;
        $residentCountryId = $inputs['residentCountryId'] ?? null;
        $nationalityCountryId = $inputs['nationalityCountryId'] ?? null;
        $education = $inputs['education'] ?? null;
        $financial = $inputs['financial'] ?? null;
        $mariageStatues = $inputs['mariageStatues'] ?? null;
        $mariageKind = $inputs['mariageKind'] ?? null;
        $workField = $inputs['workField'] ?? null;
        $ageFrom = $inputs['ageFrom'] ?? null;
        $ageTo = $inputs['ageTo'] ?? null;
        $active = $inputs['active'] ?? null;
        $userGender = $inputs['userGender'] ?? null;
        $packageId = $inputs['packageId'] ?? null;
        $userName = $inputs['userName'] ?? null;
        $from = $inputs['from'] ?? null;
        $to = $inputs['to'] ?? null;
        $search = $inputs['search'] ?? null;
        $cityId = $inputs['cityId'] ?? null;

        $sql = " select users.id,userName,age,gender,available,lastAccess,packageId,residentCountryId,
                    countries1.nameAr as residentCountryName,nationalityCountryId,countries2.nameAr as nationalityCountryName,
                    cityId,cities.nameAr as cityName,creationDate,lastAccess,mariageStatues,users.active,susbendedTillDate ,
                    if(susbendedTillDate >= CURDATE() ,1,0) as susbended,mobileType ,
                    detectedCountry
                    from users inner join countries as countries1 inner join countries as countries2
                    inner join cities 
                    on users.residentCountryId = countries1.id and users.nationalityCountryId = countries2.id
                    and users.cityId=cities.id 
                    where  1=1 ";
        $sql .= isset($search) ? " and (trim(userName) = trim('$search') or mobile like '%$search%' )" : " ";
        $sql .= isset($ageTo) ? " and (trim(age) = trim('$ageTo') or mobile like '$ageTo' )" : " ";

        $sql .= (isset($active) and $active != -1 and $active != 2) ? " and users.active=$active " : "";
        $sql .= ($active == 2) ? " and susbendedTillDate >= CURDATE() " : "";

        $sql .= isset($userGender) ? " and gender=$userGender" : "";
        // $sql.= isset($userName)? " and userName like '%$userName%'" : "";


        $sql .= isset($residentCountryId) && ($residentCountryId > 0) ? " and residentCountryId=$residentCountryId" : "";
        $sql .= isset($nationalityCountryId) && ($nationalityCountryId > 0) ? " and nationalityCountryId=$nationalityCountryId" : "";
        $sql .= isset($education) && ($education > 0) ? " and education=$education" : "";
        $sql .= isset($financial) && ($financial > 0) ? " and financial=$financial" : "";
        $sql .= isset($mariageStatues) && ($mariageStatues > 0) ? " and mariageStatues=$mariageStatues" : "";
        $sql .= isset($mariageKind) && ($mariageKind > 0) ? " and mariageKind=$mariageKind" : "";
        $sql .= isset($workField) && ($workField > 0) ? " and workField=$workField" : "";
        $sql .= isset($ageFrom) && ($ageFrom > 0) ? " and age>=$ageFrom" : "";
        $sql .= isset($ageTo) && ($ageTo > 0) ? " and age<=$ageTo" : "";
        $sql .= isset($from) ? " and creationDate>='$from'" : "";
        $sql .= isset($to) ? " and creationDate<='$to'" : "";
        $sql .= isset($packageId) && ($packageId == 0) ? " and packageId=0" : "";
        $sql .= isset($packageId) && ($packageId == 1) ? " and packageId>0" : "";

        $sql .= isset($packageId) && ($packageId > 1) ? " and packageId=$packageId" : "";
        $sql .= isset($cityId) && ($cityId > 0) ? " and cityId=$cityId" : "";


        $sql .= " order by ";
        $sql .= isset($orderBy) ? "$orderBy" : "id";
        $sql .= " desc limit $page,$pageSize ";

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

    public function listPurchases(Request $request, Response $response, $args,$internal=0)
    {
        $responseData = array();
        $inputs=array();
        $inputs = $request->getParsedBody();
        $pageSize =  $this->pageSize;
        $page = isset($inputs['page'])? $inputs['page']:0;
        $page*=$pageSize;

        $residentCountryId = $inputs['residentCountryId'] ?? null;
        $packages = $inputs['packageId'] ?? null;
        $nationalityCountryId = $inputs['nationalityCountryId'] ?? null;
        $gender = $inputs['gender'] ?? null;
        $from = $inputs['from'] ?? null;
        $to = $inputs['to'] ?? null;
        $search = $inputs['search'] ?? null;
        $sqlWhere="";
        
        $sql = " select purchases.*,users.userName as name,users.gender,users.mobile,users.residentCountryId
                    ,packages.title , countries.nameAr 
                    from purchases inner join users inner join packages inner join countries 
                    on purchases.userId = users.id and purchases.packageId = packages.id and 
                    users.residentCountryId = countries.id where deleted = 0 ";

        // $sql = " select purchases.*,users.userName as name,users.gender,users.mobile,users.residentCountryId
        //             ,packages.title , countries.nameAr
        //             from purchases inner join users inner join packages inner join countries 
        //             on purchases.userId = users.id and purchases.packageId = packages.id and 
        //             users.residentCountryId = countries.id where deleted = 0  ";
                    
        $sqlWhere.=isset($search)? " and (userName like '%$search%' or mobile = '$search' or users.id = '$search'
                    or countries.nameAr like '%$search%' or packages.title like '%$search%' or paymentRefrence like '%$search%')" : " ";            

        $sqlWhere.=isset($userGender)? " and gender=$userGender" : "";            
        $sqlWhere.= isset($residentCountryId)&&($residentCountryId>0)? " and residentCountryId=$residentCountryId" : "";
        $sqlWhere.= isset($packages)&&($packages>0)? " and purchases.packageId=$packages" : "";
        // $sqlWhere.= isset($nationalityCountryId)&&($nationalityCountryId>0)? " and residentCountryId=$residentCountryId" : "";

        $sqlWhere.= (isset($from)&& isset($to))? " and date(purchaseDateTime)>='$from' and date(purchaseDateTime)<='$to'" : "";
        $sql.=$sqlWhere;
        $sql.=" order by purchases.id desc limit $page,$pageSize ";

        //echo $sql;        
        
        $responseData = array();
    
          try{
                $sth = $this->db->prepare($sql);
                $sth->execute();
                $data = $sth->fetchAll();
                $responseData['status'] = 'success'; 

                $responseData['rowsCount'] = count($data); 

                $responseData['data'] = $data; 

                $sql = "select count(purchases.id) as rowsCount from purchases inner join users inner join packages inner join countries 
                on purchases.userId = users.id and purchases.packageId = packages.id and 
                users.residentCountryId = countries.id where deleted = 0 and paymentValue>0 ";
                $sql.=$sqlWhere;
                //echo $sql;
                $sth = $this->db->prepare($sql);
                $sth->execute();
                $data = $sth->fetchAll();
                $responseData['totalResult'] = $data[0]['rowsCount']; 


            }
          catch (\PDOException $e) {
            
                $responseData = array();
                $responseData['status'] = 'error'; 
                $responseData['errorCode'] =  $e->getMessage(); 
                }
       
        return JsonResponse::withJson($response, json_encode($responseData), 200);

         
       
    } 


    public function listFailedPurchases(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();
        $pageSize =  $this->pageSize;
        $page = isset($args['page']) ? $args['page'] : 0;
        $page *= $pageSize;




        $sql = " select failedPurchases.*,users.userName as name,users.gender,users.mobile,users.residentCountryId
                ,packages.title , countries.nameAr,mobileType,payMethod
                from failedPurchases inner join users inner join packages inner join countries 
                on failedPurchases.userId = users.id and failedPurchases.packageId = packages.id and 
                users.residentCountryId = countries.id where status=0 ";
        $sql .= " order by failedPurchases.id desc limit $page,$pageSize ";



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

    public function deleteFailedPurchases(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();
        $blogId = $inputs['blogId'];
        $sql = "delete from failedPurchases ";

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $responseData['status'] = 'success';
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }
        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    public function susbendUser(Request $request, Response $response, $args)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();
        $responseData = array();

        $userId = $inputs['userId'];
        $tillDate = $inputs['tillDate'];

        $sql = " update users set susbendedTillDate='$tillDate' where id=$userId";
        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;

            // ---Insert into Notifications -----
            $title = "تم حظر عضويتك";
            $message = "لمخالفتك شروط وأحكام زفاف حتي تاريخ $tillDate";
            $sql = "insert into notifications (otherId,notiType,title,message) values($userId,0,'$title','$message')";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            //-----Send Notification to app and web ----

            $sql = "select deviceToken,webToken from users where id=$userId";
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

            $deviceToken = $data[0]['deviceToken'];
            $webToken = $data[0]['webToken'];
            $services = new Services($this->container);
            $params = array();
            $params['privateData'] = '{"type":"0"}';
            $params['title'] = $title;
            $params['message'] = $message;
            $params['multi'] = 1;

            $params['notificaionsTokens'] = "$deviceToken,$webToken";
            $services->sendNotification($params);
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }


        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function reActiveUser(Request $request, Response $response, $args)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();
        $responseData = array();

        $userId = $inputs['userId'];

        $sql = " update users set active=1,susbendedTillDate=NULL where id=$userId";
        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();

            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;

            //    // ---Insert into Notifications -----
            //     $title = "تم إيقاف عضويتك";
            //     $message = "تم إيقاف عضويتك لمخالفتك الشروط والأحكام حتى تاريخ $tillDate يمكنك التواصل معنا من خلال صفحة الرسائل";
            //     $sql = "insert into notifications (otherId,notiType,title,message) values($userId,0,'$title','$message')";
            //     $sth = $this->db->prepare($sql);
            //     $sth->execute();      
            //      //-----Send Notification to app and web ----

            //     $sql = "select deviceToken,webToken from users where id=$userId";
            //     $sth = $this->db->prepare($sql);
            //     $sth->execute();
            //     $data = $sth->fetchAll();

            //     $deviceToken = $data[0]['deviceToken'];
            //     $webToken = $data[0]['webToken'];
            //     $services = new Services($this->container);
            //     $params = array();
            //     $params['privateData'] = '{"type":"0"}';
            //     $params['title'] = $title;
            //     $params['message'] = $message;
            //     $params['multi']=1;

            //     $params['notificaionsTokens']="$deviceToken,$webToken";
            //      $services->sendNotification($params);


        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }


        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function adminNotification(Request $request, Response $response, $args)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();
        // print_r($inputs);
        // return;
        $userId = $inputs['userId'];
        $topic = $inputs['topic'];
        $title = $inputs['title'];
        $message = $inputs['message'];
        $type = isset($inputs['type']) ? $inputs['type'] : 0;

        $gender = $inputs['gender'];
        $country = isset($inputs['country']) ? $inputs['country'] : 'all';
        if ($gender == 'all' && $country == 'all')
            $condition = "('all' in topics)";
        if ($gender != 'all' && $country == 'all')
            $condition = "('$gender' in topics)";

        if ($gender == 'all' && $country != 'all')
            $condition = "('country-" . $country . "' in topics)";

        if ($gender != 'all' && $country != 'all')
            $condition = "('country-" . $country . "' in topics && '$gender' in topics)";

        $responseData = array();

        try {

            // ---Insert into Notifications -----
            if (isset($userId)) {
                $sql = "select deviceToken,webToken from users where id=$userId";
                $sth = $this->db->prepare($sql);
                $sth->execute();
                $data = $sth->fetchAll();

                $deviceToken = $data[0]['deviceToken'];
                $webToken = $data[0]['webToken'];
                $params['notificaionsTokens'] = "$deviceToken,$webToken";
                $params['multi'] = 1;

                $sql = "insert into notifications (otherId,notiType,title,message) values($userId,$type,'$title','$message')";
            } else {
                $gender = ($gender == 'man') ? '0' : (($gender == 'woman') ? '1' : '2');
                $residentCountryId = ($country == 'all') ? '0' : $country;

                $sql = "insert into notifications (fixed,notiType,title,message,notiGender,topic,residentCountryId) 
                            values(1,0,'$title','$message',$gender,'$topic','$residentCountryId')";
                $params['topic'] = $topic;
            }

            $sth = $this->db->prepare($sql);
            $sth->execute();
            //-----Send Notification to app and web ----

            $params['privateData'] = '{"type":"' . $type . '"}';
            $params['title'] = $title;
            $params['message'] = $message;
            $params['condition'] = $condition;

            $services = new Services($this->container);
            $services->sendNotification($params);
            $responseData['status'] = 'success';
            $responseData['rowsCount'] = 1;
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }


        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function subscripePackage(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();
        if ($internal == 0) {
            $userId = $inputs['userId'];
            $packageId =  $inputs['packageId'];
            $paymentRefrence =  'Manual from Admin';
            $paymentValue =  0;
            $agentId = 1;
        } else {
            $userId = $args['userId'];
            $packageId =  $args['packageId'];
            $agentId =  $args['agentId'];
            $paymentRefrence =  'Agent';
            $paymentValue =  0;
        }

        $sql0 = "select id,usdValue from packages where id=$packageId";
        $sth = $this->db->prepare($sql0);
        $sth->execute();
        $data = $sth->fetchAll();
        $paymentValue =  $data[0]['usdValue'];
        //    if($paymentValue == 25) $paymentValue = 20;
        //    $packageDays = ($paymentValue==0)? 'DAY' : "MONTH";
        $packageDays =  'DAY';

        list($usec, $sec) = explode(" ", microtime());

        $transactionId = mt_rand(100000000, 999999999) . substr($usec, 5);


        $sql = "insert into purchases (userId,packageId,paymentRefrence,paymentValue,agentId,transactionId) 
            values($userId,$packageId,'$paymentRefrence',$paymentValue,$agentId,'$transactionId')";
        $sql1 = "update users set packageRenewDate = DATE_ADD(if(users.packageId=0,CURDATE(),packageRenewDate),  
        INTERVAL (select validFor from packages where id=$packageId) $packageDays) ,packageId=$packageId where users.id=$userId";
        $responseData = array();

        try {



            if ($packageId > 0) {
                $sth = $this->db->prepare($sql);
                $sth->execute();
            }
            $sth = $this->db->prepare($sql1);
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

    public function getAdminPackages(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $sql = " select * from packages order by validFor  ";
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

    public function getUserChatList(Request $request, Response $response, $args, $internal = 0)
    {
        $userId = $args['userId'];

        $sql = "select chats.id,lastMessagetime,lastMessage,lastMessageType,lastSender,readed,
                @otherId :=if(user1Id=$userId,user2Id,user1Id) as otherId,

                if(allowImageList.allowImage=1,if(user1Id=$userId,users2.profileImage,users1.profileImage),'') as userImage,
                if(user1Id=$userId,users2.userName,users1.userName) as otherName,
                if(user1Id=$userId,users2.detectedCountry,users1.detectedCountry) as detectedCountry,
                if(user1Id=$userId,users2.detectedCountry,users1.detectedCountry) as usersDetectedCountry1,

                
                if(user1Id=$userId,users2.available,users1.available) as available,
                if(user1Id=$userId,users2.lastAccess,users1.lastAccess) as lastAccess
        
                from chats inner join users as users1 on chats.user1Id = users1.id
                inner join users as users2 on chats.user2Id = users2.id 
                inner join 
                (select sum(if(listType=2,1,0)) as allowImage from lists 
                where userId=@otherId and otherId=$userId and listType=2) as allowImageList       
                where user1Id = $userId or user2Id = $userId order by chats.lastMessagetime desc";

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

    public function getAllUsersChatList(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $pageSize = $this->pageSize;
        $page = isset($args['page']) ? $args['page'] : 0;
        $page *= $pageSize;


        $sql = "select chats.id,lastMessagetime,lastMessage,lastMessageType,lastSender,readed,
        user1Id as userId1,users1.userName as userName1,users1.detectedCountry as usersDetectedCountry1,
        users1.available as userAvailable1,users1.lastAccess as userLastAccess1,
        user2Id as userId2,users2.userName as userName2,users2.detectedCountry as usersDetectedCountry2,
        users2.available as userAvailable2,users2.lastAccess as userLastAccess2
                 
                        from chats inner join users as users1 on chats.user1Id = users1.id
                        inner join users as users2 on chats.user2Id = users2.id 
                        where lastMessage IS NOT NULL
                        order by chats.lastMessageTime desc limit $page,$pageSize";

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

    public function openChat(Request $request, Response $response, $args, $internal = 0)
    {
        $chatId =  $args['chatId'];

        $sql = " select chatMessages.id,message,type,messageTime,senderId,userName,readed
        from chatMessages inner join users  on chatMessages.senderId = users.id
        where chatId=$chatId order by id desc ";

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
        $password =  $inputs['password'];
        //$password =  md5($inputs['password']);
        $active = $inputs['active'];
        $imageFile =  $inputs['imageFile'];
        $localValue =  $inputs['localValue'];

        $uploadedFiles = $request->getUploadedFiles();
        if (!empty($uploadedFiles['attachment'])) {
            $services = new Services($this->container);
            $fileUploaded = $services->upload($request, $response, $args, true);
            $imageFile = $fileUploaded['fileName'];
        }


        try {
            // $sql = " select mobile from agents where mobile='$mobile' or email='$email'";
            // $sth = $this->db->prepare($sql);
            // $sth->execute();
            // $data = $sth->fetchAll();
            // if(count($data)>0){//Duplicate email or mobile
            //     $responseData['status'] = 'error'; 
            //     $responseData['rowsCount'] = 0; 
            //     $responseData['errorCode'] = '1'; 
            //     $responseData['errorMessage'] = 'Duplicate email or mobile'; 

            //     }
            // else
            {
                $sql = "insert into agents  (name,email,password,countryId,mobile,whats,nationalId,localValue,active) 
                values('$name','$email','$password',$countryId,'$mobile','$whats','$imageFile','$localValue',$active)";

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

    public function updateAgent(Request $request, Response $response, $args, $internal = 0)
    {

        $inputs = array();
        $inputs = $request->getParsedBody();
        $responseData = array();

        $agentId = $inputs['agentId'];
        $name = $inputs['name'];
        $email = $inputs['email'];
        $countryId =  $inputs['countryId'];
        $mobile =  $inputs['mobile'];
        $whats =  $inputs['whats'];
        $password =  $inputs['password'];
        // $password =  md5($inputs['password']);
        $active = $inputs['active'];
        $imageFile =  $inputs['imageFile'];
        $localValue =  $inputs['localValue'];

        $uploadedFiles = $request->getUploadedFiles();
        if (!empty($uploadedFiles['attachment'])) {
            $services = new Services($this->container);
            $fileUploaded = $services->upload($request, $response, $args, true);
            $imageFile = $fileUploaded['fileName'];
        }

        try {
            //  $sql = " select mobile from agents where (mobile='$mobile' or email='$email') and id!=$agentId";
            //  $sth = $this->db->prepare($sql);
            //  $sth->execute();
            //  $data = $sth->fetchAll();
            //  if(count($data)>0){//Duplicate email or mobile
            //      $responseData['status'] = 'error'; 
            //      $responseData['rowsCount'] = 0; 
            //      $responseData['errorCode'] = '1'; 
            //      $responseData['errorMessage'] = 'Duplicate email or mobile'; 

            //      }
            //  else
            {
                $sql = "update agents set name ='$name' ,email = '$email',";
                if ($password) $sql .= "password = '$password',";
                $sql .= "countryId = $countryId,mobile = '$mobile' ,whats='$whats',active = $active
                    ,localValue='$localValue',nationalId='$imageFile' where id = $agentId";

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

    public function getAgentDetails(Request $request, Response $response, $args, $internal = 0)
    {
        $id = $args['id'];

        $sql = "select agents.*,countries.nameAr as countryName from agents inner join countries on agents.countryId = 
            countries.id where agents.id=$id";
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

    public function deleteAgent(Request $request, Response $response, $args, $internal = 0)
    {
        $id = $args['id'];

        $sql = "delete from agents where id=$id";
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

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
    public function listAgents(Request $request, Response $response, $args, $internal = 0)
    {

        $sql = "select agents.*,countries.nameAr as countryName from agents inner join countries on agents.countryId = 
            countries.id order by id desc";
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

    public function addCopouns(Request $request, Response $response, $args, $internal = 0)
    {

        $inputs = array();
        $inputs = $request->getParsedBody();
        $responseData = array();

        $agentId = $inputs['agentId'];
        $packageId = $inputs['packageId'];
        $copounsCount = $inputs['copounsCount'];


        $sql = "insert into copouns  (agentId,copoun,packageId,addedDate) 
                values";
        for ($i = 0; $i < $copounsCount; $i++) {
            list($usec, $sec) = explode(" ", microtime());
            $copoun = mt_rand(100000000, 999999999) . substr($usec, 5);
            $sql .= "($agentId,$copoun,$packageId,NOW())";
            $sql .= ($i + 1 < $copounsCount) ? ',' : '';
        }
        //  echo $sql; 

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $responseData['status'] = 'success';
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function deleteCopoun(Request $request, Response $response, $args, $internal = 0)
    {
        $id = $args['id'];

        $sql = "delete from copouns where id=$id and  isNull(usedDate)";
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $sql = "update copouns set active=2 where id=$id ";
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
    public function deleteAllCopouns(Request $request, Response $response, $args, $internal = 0)
    {
        $agentId = isset($args['agentId']) ? $args['agentId'] : 0; // 0 all 

        $responseData = array();

        try {
            $sql = "update copouns set active=2 where agentId=$agentId and  !isNull(usedDate)";
            $sth = $this->db->prepare($sql);
            $sth->execute();

            // $sql = "update copouns set active=2 where agentId=$agentId ";
            // $sth = $this->db->prepare($sql);
            // $sth->execute();


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

    public function agentLogin(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();

        $inputs = array();
        $inputs = $request->getParsedBody();
        $email = $inputs['email'];
        $password =  md5($inputs['password']);

        $sql = array();
        $sql[0] = "select id,name,email,active from agents where email='$email' and password='$password' and active=1";
        $sql[1] = "update agents set lastAccess=CURRENT_TIMESTAMP() 
                     where id=:userId";

        $responseData = array();
        try {
            $sth = $this->db->prepare($sql[0]);
            $sth->execute();
            $data = $sth->fetchAll();

            if (sizeof($data) > 0) //user exists
            {
                $responseData['status'] = 'success';
                $responseData['token'] = $this->generateToken($data[0]['id'], 2);
                $responseData['pathToUpload'] = $this->pathToUpload;
                $responseData['data'] = $data;
                //---Update user data --
                $sth = $this->db->prepare($sql[1]);
                $sth->bindParam(':userId', $data[0]['id']);
                $sth->execute();
            } else {
                $responseData['status'] = 'error';
                $responseData['rowsCount'] = 0;
                $responseData['errorMessage'] = 'Agent not exists ';
            }
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function listCopouns(Request $request, Response $response, $args, $internal = 0)
    {
        $userId = $request->getAttribute("userId");
        $userType = $request->getAttribute("type");
        $status = isset($args['status']) ? $args['status'] : 0; // 0 all 1 new 2 used
        $agentId = isset($args['agentId']) ? $args['agentId'] : 0; // 0 all 
        $pageSize =  $this->pageSize;

        $page = isset($args['page']) ? $args['page'] : 0;
        $page *= $pageSize;

        //echo "user:$userId,userType:$userType,status:$status";

        if ($userType == 2) { // agent
            $sql = "select copouns.*,if(userId>0,users.userName,'') as copounUser from copouns inner join users 
            on copouns.userId = users.id where agentId=$userId and copouns.active=1 ";
            $sql .= ($status > 0) ? (($status == 1) ? ' and userId=0' : ' and userId>0') : '';
            $sql .= " order by usedDate desc,id limit $page,$pageSize";
        } else {
            $sql = "select copouns.*,if(userId>0,users.userName,'') as copounUser,agents.name as agentName from 
                    copouns inner join users inner join agents on copouns.userId = users.id and copouns.agentId = agents.id 
                    where copouns.active=1 ";
            $sql .= ($agentId > 0) ? " and agentId=$agentId" : '';
            $sql .= ($status > 0) ? (($status == 1) ? ' and userId=0' : ' and userId>0') : '';
            $sql .= " order by usedDate desc,id limit $page,$pageSize";
        }
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

    public function assignCopoun(Request $request, Response $response, $args, $internal = 0)
    {

        $inputs = array();
        $inputs = $request->getParsedBody();
        $responseData = array();

        $agentId = $request->getAttribute("userId");
        $copoundId = $inputs['copoundId'];
        $userId = $inputs['userId'];
        $packageId = $inputs['packageId'];

        try {
            $sql = "update copouns set userId=$userId,usedDate=NOW() where id=$copoundId and isNull(usedDate)";

            $sth = $this->db->prepare($sql);
            $sth->execute();
            if ($sth->rowCount() > 0) {
                $args['userId'] = $userId;
                $args['packageId'] = $packageId;
                $args['agentId'] = $agentId;
                $this->subscripePackage($request,  $response, $args, 1);
            }
            $responseData['status'] = 'success';
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

    public function deleteAdminNotifications(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();
        $blogId = $inputs['blogId'];
        $sql = "delete from notifications where fixed=1";

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $responseData['status'] = 'success';
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }
        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }

public function readAllMessages(Request $request, Response $response, $args, $internal = 0)
{
    // Retrieve and sanitize the 'type' parameter
    $types = $request->getQueryParams()['type'] ?? '';

    if ($types === '') {
        $responseData = [
            'status' => 'error',
            'errorCode' => 'Type parameter is missing or empty'
        ];
        return JsonResponse::withJson($response, json_encode($responseData), 400);
    }

    // Ensure 'types' is an array
    $typesArray = explode(',', $types);
    $placeholders = implode(',', array_fill(0, count($typesArray), '?'));

    $sql = "UPDATE messages SET active = 0, readed = 1 WHERE reasonId IN ($placeholders)";

    try {
        $sth = $this->db->prepare($sql);
        $sth->execute($typesArray);
        $responseData['status'] = 'success';
    } catch (\PDOException $e) {
        $responseData = [
            'status' => 'error',
            'errorCode' => $e->getMessage()
        ];
    }

    return JsonResponse::withJson($response, json_encode($responseData), 200);
}
    
    

public function unReadAllMessages(Request $request, Response $response, $args, $internal = 0)
{
    
    
    $types = $request->getQueryParams()['type'] ?? '';

    if ($types === '') {
        $responseData = [
            'status' => 'error',
            'errorCode' => 'Type parameter is missing or empty'
        ];
        return JsonResponse::withJson($response, json_encode($responseData), 400);
    }

    // Ensure 'types' is an array
    $typesArray = explode(',', $types);
    $placeholders = implode(',', array_fill(0, count($typesArray), '?'));

    $sql = "UPDATE messages SET active = 1 WHERE reasonId IN ($placeholders)";

    try {
        $sth = $this->db->prepare($sql);
        $sth->execute($typesArray);
        $responseData['status'] = 'success';
    } catch (\PDOException $e) {
        $responseData = [
            'status' => 'error',
            'errorCode' => $e->getMessage()
        ];
    }

    return JsonResponse::withJson($response, json_encode($responseData), 200);
}


    // public function unReadAllMessages(Request $request, Response $response, $args, $internal = 0)
    // {
    //     $inputs = array();
    //     $inputs = $request->getParsedBody();
    //     $sql = "update messages set active = 1";

    //     try {
    //         $sth = $this->db->prepare($sql);
    //         $sth->execute();
    //         $responseData['status'] = 'success';
    //     } catch (\PDOException $e) {

    //         $responseData = array();
    //         $responseData['status'] = 'error';
    //         $responseData['errorCode'] =  $e->getMessage();
    //     }
    //     return JsonResponse::withJson($response, json_encode($responseData), 200);
    // }

    public function listPendingPhotos(Request $request, Response $response, $args, $internal = 0)
    {

        $pageSize =  $this->pageSize;

        $page = isset($args['page']) ? $args['page'] : 0;
        $page *= $pageSize;


        $sql = "select id,userName,gender, tempProfileImage,photoUploadeDate,detectedCountry from users where active = 1 and !isNull(tempProfileImage)";
        $sql .= " order by photoUploadeDate asc limit $page,$pageSize";

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

    public function confirmUploadPhoto(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();
        $userId = $inputs['userId'];
        // ---Insert into Notifications -----
        $message = 'تمت الموافقة على عرض صورتك الشخصية';
        $title = 'صورتك الشخصية';
        $sql = "insert into notifications (otherId,notiType,title,message) values($userId,0,'$title','$message')";
        $sth = $this->db->prepare($sql);
        $sth->execute();
        //-----Send Notification to app and web ----

        $sql = "select deviceToken,webToken from users where id=$userId";
        $sth = $this->db->prepare($sql);
        $sth->execute();
        $data = $sth->fetchAll();

        $deviceToken = $data[0]['deviceToken'];
        $webToken = $data[0]['webToken'];
        $services = new Services($this->container);
        $params = array();
        $params['privateData'] = '{"type":"0"}';
        $params['title'] = $title;
        $params['message'] = $message;
        $params['multi'] = 1;

        $params['notificaionsTokens'] = "$deviceToken,$webToken";
        $services->sendNotification($params);



        $sql1 = "update users set profileImage = tempProfileImage where id=$userId";
        $sql2 = "update users set tempProfileImage = NULL where id=$userId";

        $responseData = array();

        try {
            $sth = $this->db->prepare($sql1);
            $sth->execute();
            $sth = $this->db->prepare($sql2);
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

    public function refuseUploadPhoto(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();
        $inputs = array();
        $inputs = $request->getParsedBody();
        $userId = $inputs['userId'];
        // ---Insert into Notifications -----
        $message = 'تم رفض الصورة لمخالفتها شروط زفاف';
        $title = 'صورتك الشخصية';
        $sql = "insert into notifications (otherId,notiType,title,message) values($userId,0,'$title','$message')";
        $sth = $this->db->prepare($sql);
        $sth->execute();
        //-----Send Notification to app and web ----

        $sql = "select deviceToken,webToken from users where id=$userId";
        $sth = $this->db->prepare($sql);
        $sth->execute();
        $data = $sth->fetchAll();

        $deviceToken = $data[0]['deviceToken'];
        $webToken = $data[0]['webToken'];
        $services = new Services($this->container);
        $params = array();
        $params['privateData'] = '{"type":"0"}';
        $params['title'] = $title;
        $params['message'] = $message;
        $params['multi'] = 1;

        $params['notificaionsTokens'] = "$deviceToken,$webToken";
        $services->sendNotification($params);


        $sql = "update users set tempProfileImage = NULL where id=$userId";
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

    public function sendGeneralMessage(Request $request, Response $response, $args, $internal = 0)
    {
        $inputs = array();
        $inputs = $request->getParsedBody();
        // print_r($inputs);
        // return;

        $title = $inputs['title'];
        $message = $inputs['message'];
        $adminImage = $inputs['adminImage'];


        $gender = $inputs['gender'];
        $country = $inputs['country'];
        $mesGender = ($gender == 'all') ? '2' : (($gender == 'man') ? '0' : '1');
        $residentCountryId = ($country == 'all') ? '0' :  intval($country);

        try {
            $sql = "insert into messages (userId,reasonId,title,message,owner,adminImage,mesGender,residentCountryId) 
                    values 
                    ('0','4','$title','$message','1','$adminImage','$mesGender','$residentCountryId')";
            // echo $sql;

            $sth = $this->db->prepare($sql);
            $sth->execute();
            $sql = "insert into notifications (fixed,notiType,title,message,notiGender,residentCountryId) 
                    values(1,12,'رساله من زفاف','$title',$mesGender,'$residentCountryId')";


            $sth = $this->db->prepare($sql);
            $sth->execute();
            //-----Send Notification to app and web ----
            if ($gender == 'all' && $country == 'all')
                $condition = "('all' in topics)";
            if ($gender != 'all' && $country == 'all')
                $condition = "('$gender' in topics)";

            if ($gender == 'all' && $country != 'all')
                $condition = "('country-" . $country . "' in topics)";

            if ($gender != 'all' && $country != 'all')
                $condition = "('country-" . $country . "' in topics && '$gender' in topics)";

            $params['condition'] = $condition;

            $params['privateData'] = '{"type":"12"}';
            $params['title'] = 'رسالة من زفاف';
            $params['message'] = $title;

            $services = new Services($this->container);
            $services->sendNotification($params);

            $responseData['status'] = 'success';
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }
        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }


    //---telesales
    public function addTelesales(Request $request, Response $response, $args, $internal = 0)
    {

        $inputs = array();
        $inputs = $request->getParsedBody();
        $responseData = array();

        $name = $inputs['name'];
        $email = $inputs['email'];
        $countryId =  $inputs['countryId'];
        $mobile =  $inputs['mobile'];
        $whats =  $inputs['whats'];
        $password =  $inputs['password'];
        //$password =  md5($inputs['password']);
        $active = $inputs['active'];
        $code =  $inputs['code'];
        $commision =  $inputs['commision'];
        try {
            // $sql = " select mobile from agents where mobile='$mobile' or email='$email'";
            // $sth = $this->db->prepare($sql);
            // $sth->execute();
            // $data = $sth->fetchAll();
            // if(count($data)>0){//Duplicate email or mobile
            //     $responseData['status'] = 'error'; 
            //     $responseData['rowsCount'] = 0; 
            //     $responseData['errorCode'] = '1'; 
            //     $responseData['errorMessage'] = 'Duplicate email or mobile'; 

            //     }
            // else
            {
                $sql = "insert into telesales  (name,email,password,countryId,mobile,whats,code,commision,active) 
                values('$name','$email','$password',$countryId,'$mobile','$whats','$code','$commision',$active)";

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

    public function updateTelesales(Request $request, Response $response, $args, $internal = 0)
    {

        $inputs = array();
        $inputs = $request->getParsedBody();
        $responseData = array();

        $id = $inputs['id'];
        $name = $inputs['name'];
        $email = $inputs['email'];
        $countryId =  $inputs['countryId'];
        $mobile =  $inputs['mobile'];
        $whats =  $inputs['whats'];
        $password =  $inputs['password'];
        // $password =  md5($inputs['password']);
        $active = $inputs['active'];
        $code =  $inputs['code'];
        $commision =  $inputs['commision'];
        $balance =  $inputs['balance'];
        $payedBalance =  $inputs['payedBalance'];




        try {
            //  $sql = " select mobile from agents where (mobile='$mobile' or email='$email') and id!=$agentId";
            //  $sth = $this->db->prepare($sql);
            //  $sth->execute();
            //  $data = $sth->fetchAll();
            //  if(count($data)>0){//Duplicate email or mobile
            //      $responseData['status'] = 'error'; 
            //      $responseData['rowsCount'] = 0; 
            //      $responseData['errorCode'] = '1'; 
            //      $responseData['errorMessage'] = 'Duplicate email or mobile'; 

            //      }
            //  else
            {
                $sql = "update telesales set name ='$name' ,email = '$email',";
                if ($password) $sql .= "password = '$password',";
                $sql .= "countryId = $countryId,mobile = '$mobile' ,whats='$whats',active = $active
                    ,code='$code',commision='$commision',balance='$balance',payedBalance='$payedBalance'
                     where id = $id";

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

    public function deleteTelesales(Request $request, Response $response, $args, $internal = 0)
    {
        $id = $args['id'];

        $sql = "delete from telesales where id=$id";
        $responseData = array();

        try {
            $sth = $this->db->prepare($sql);
            $sth->execute();
            $data = $sth->fetchAll();

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
    public function listTelesales(Request $request, Response $response, $args, $internal = 0)
    {

        $sql = "select telesales.*,(balance - payedBalance) as remaningBalance,countries.nameAr as countryName from telesales inner join countries 
                on telesales.countryId = 
                countries.id order by telesales.id desc";
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

    public function getTelesalesDetails(Request $request, Response $response, $args, $internal = 0)
    {
        $id = $args['id'];

        $sql = "select telesales.*,(balance - payedBalance) as remaningBalance,
        countries.nameAr as countryName 
        from telesales inner join countries on telesales.countryId = 
            countries.id where telesales.id=$id";
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

    public function telesalesLogin(Request $request, Response $response, $args, $internal = 0)
    {
        $responseData = array();

        $inputs = array();
        $inputs = $request->getParsedBody();
        $email = $inputs['email'];
        $password =  md5($inputs['password']);
        //$password =  $inputs['password'];

        $sql = array();
        $sql[0] = "select * from telesales where email='$email' and password='$password' and active=1";
        $sql[1] = "update telesales set lastAccess=CURRENT_TIMESTAMP() 
                     where id=:userId";

        $responseData = array();
        try {
            $sth = $this->db->prepare($sql[0]);
            $sth->execute();
            $data = $sth->fetchAll();

            if (sizeof($data) > 0) //user exists
            {
                $responseData['status'] = 'success';
                $responseData['token'] = $this->generateToken($data[0]['id'], 2);
                $responseData['data'] = $data;
                //---Update user data --
                $sth = $this->db->prepare($sql[1]);
                $sth->bindParam(':userId', $data[0]['id']);
                $sth->execute();
            } else {
                $responseData['status'] = 'error';
                $responseData['rowsCount'] = 0;
                $responseData['errorMessage'] = 'Agent not exists ';
            }
        } catch (\PDOException $e) {

            $responseData = array();
            $responseData['status'] = 'error';
            $responseData['errorCode'] =  $e->getMessage();
        }

        return JsonResponse::withJson($response, json_encode($responseData), 200);
    }
    //-------

}//----Class end
