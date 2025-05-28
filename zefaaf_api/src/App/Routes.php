<?php

declare(strict_types=1);
use Slim\Interfaces\RouteCollectorProxyInterface as Group;



$app->group('/v1', function (Group $group)  {
    $group->group('/mobile', function(Group $group) {
        $group->get('/status', 'App\Controller\Mobile:getStatus');
        $group->get('/getAppSettings', 'App\Controller\Mobile:getAppSettings');

        $group->get('/getCountries/[{page}]', 'App\Controller\Mobile:getCountries');
        $group->get('/getCities/{countryId}/[{page}]', 'App\Controller\Mobile:getCities');

        $group->get('/checkMobile/{mobile}', 'App\Controller\Mobile:checkMobile');
        $group->get('/checkUserName/{userName}', 'App\Controller\Mobile:checkUserName');
        $group->post('/register', 'App\Controller\Mobile:register');
        $group->post('/login','App\Controller\Mobile:login');
        $group->post('/loginByToken','App\Controller\Mobile:loginByToken');
        $group->get('/getMyUpdates', 'App\Controller\Mobile:getMyUpdates');
        $group->post('/search', 'App\Controller\Mobile:search');
        $group->get('/getMyNotifications/{notificationtype}/{page}/[{lastChecked}]', 'App\Controller\Mobile:getMyNotifications');
        $group->post('/updateMyPassword', 'App\Controller\Mobile:updateMyPassword');
        $group->get('/getUserDetails/{id}', 'App\Controller\Mobile:getUserDetails');
        $group->post('/updateMyMobile', 'App\Controller\Mobile:updateMyMobile');
        $group->post('/changeMyStatus', 'App\Controller\Mobile:changeMyStatus');
        $group->post('/updateMySettings', 'App\Controller\Mobile:updateMySettings');
        $group->get('/getMySettings', 'App\Controller\Mobile:getMySettings');
        $group->post('/changePasswordNew', 'App\Controller\Mobile:changePasswordNew');

        
      
        $group->get('/getMyFavorites/{listType}/{page}/[{lastChecked}]', 'App\Controller\Mobile:getMyFavorites');
        $group->post('/addToMyFavorites', 'App\Controller\Mobile:addToMyFavorites');
        $group->post('/removeFromFavorites', 'App\Controller\Mobile:removeFromFavorites');
      
        
        $group->post('/requestPhoto', 'App\Controller\Mobile:requestPhoto');
        $group->post('/replyPhoto', 'App\Controller\Mobile:replyPhoto');

        $group->get('/getPackages', 'App\Controller\Mobile:getPackages');
        $group->get('/getPackages2', 'App\Controller\Mobile:getPackages2');

        $group->post('/purchasePackage', 'App\Controller\Mobile:purchasePackage');
        $group->post('/uploadMyPhoto', 'App\Controller\Mobile:uploadMyPhoto');

        $group->get('/getPostsCategories', 'App\Controller\Mobile:getPostsCategories');
        $group->get('/getPosts/{catId}/{page}', 'App\Controller\Mobile:getPosts');
        $group->get('/getPostDetails/{id}', 'App\Controller\Mobile:getPostDetails');

        $group->get('/getSuccessStories/{page}', 'App\Controller\Mobile:getSuccessStories');
        $group->get('/getStoryDetails/{id}', 'App\Controller\Mobile:getStoryDetails');

        $group->post('/addSuccessStory', 'App\Controller\Mobile:addSuccessStory');
        $group->get('/getMessagesList', 'App\Controller\Mobile:getMessagesList');
        $group->get('/getMessageDetails/{id}', 'App\Controller\Mobile:getMessageDetails');

        $group->post('/sendMessage', 'App\Controller\Mobile:sendMessage');
        $group->post('/replyMessage', 'App\Controller\Mobile:replyMessage');
        $group->post('/testSendEmail', 'App\Controller\Mobile:testSendEmail');

        
        

        $group->get('/getMyChatsList', 'App\Controller\Mobile:getMyChatsList');
        $group->post('/openChat', 'App\Controller\Mobile:openChat');
        $group->get('/getMorechatMessages/{chatId}/{page}', 'App\Controller\Mobile:getMorechatMessages');

        $group->post('/sendChatMessage', 'App\Controller\Mobile:sendChatMessage');        
        $group->get('/getStickers', 'App\Controller\Mobile:getStickers');
        
        $group->post('/testNotification', 'App\Controller\Mobile:testNotification');
        $group->get('/getWebHome/{gender}/[{residentCountryId}]', 'App\Controller\Mobile:getWebHome');
        $group->post('/terminateMyAccount', 'App\Controller\Mobile:terminateMyAccount');
        $group->get('/requestPay/{packageId}', 'App\Controller\Mobile:requestPay');
        $group->post('/confirmPayment', 'App\Controller\Mobile:confirmPayment');

        $group->post('/requestChangePassword', 'App\Controller\Mobile:requestChangePassword');
        $group->post('/changePassword', 'App\Controller\Mobile:changePassword');
        $group->post('/updateMySearchSetings', 'App\Controller\Mobile:updateMySearchSetings');
        $group->get('/getMySearch/{page}/[{orderBy}]', 'App\Controller\Mobile:getMySearch');
        $group->get('/getMySearchSettings', 'App\Controller\Mobile:getMySearchSettings');
        $group->get('/checkForSpamChat/{message}', 'App\Controller\Mobile:checkForSpamChat');
        $group->post('/confirmReadedChat', 'App\Controller\Mobile:confirmReadedChat');
        $group->post('/confirmPlayChatRecord', 'App\Controller\Mobile:confirmPlayChatRecord');
        $group->get('/getPaymentTokens', 'App\Controller\Mobile:getPaymentTokens');
        $group->post('/uploadSoundFile','App\Controller\Mobile:uploadSoundFile');

        $group->post('/vapPay','App\Controller\Mobile:vapPay');
        $group->post('/confirmVapPayment','App\Controller\Mobile:confirmVapPayment');
        $group->post('/confirmPaypalPayment','App\Controller\Mobile:confirmPaypalPayment');
        
        $group->get('/requestWebPay/{userId}/{packageId}','App\Controller\Mobile:requestWebPay');
        
        $group->post('/hideChat','App\Controller\Mobile:hideChat');
        $group->post('/hideAllChats','App\Controller\Mobile:hideAllChats');
        $group->post('/deleteChatMessage','App\Controller\Mobile:deleteChatMessage');

        
        
        $group->post('/updateBlogViews','App\Controller\Mobile:updateBlogViews');
        $group->get('/getAgents','App\Controller\Mobile:getAgents');
        $group->post('/addAgent','App\Controller\Mobile:addAgent');
        $group->post('/tryPurchasePackage','App\Controller\Mobile:tryPurchasePackage');
        $group->post('/cancelRequestPhoto','App\Controller\Mobile:cancelRequestPhoto');
        $group->post('/deleteMyProfileImage','App\Controller\Mobile:deleteMyProfileImage');

        
        $group->post('/marriageRequest', 'App\Controller\Mobile:marriageRequest');
        $group->post('/requestMobile', 'App\Controller\Mobile:requestMobile');
        $group->post('/cancelRequestMobile', 'App\Controller\Mobile:cancelRequestMobile');
        $group->post('/replyRequestMobile', 'App\Controller\Mobile:replyRequestMobile');

        ///-----
                             
         });
    
    $group->group('/crud', function(Group $group) {
            $group->get('/status', 'App\Controller\Crud:getStatus');
            $group->post('/login','App\Controller\Crud:login');
            $group->post('/updateMyPassword','App\Controller\Crud:updateMyPassword');
          
            
            $group->post('/uploadFile','App\Controller\Crud:uploadFile');

            
            $group->get('/getAdminUser/{id}','App\Controller\Crud:getAdminUser');
            $group->post('/addAdminUser','App\Controller\Crud:addAdminUser');
            $group->post('/updateAdminUser','App\Controller\Crud:updateAdminUser');
            $group->post('/reActiveUsers', 'App\Controller\Crud:reActiveUsers');

                  
            $group->get('/get/{table}/{page}/[{order}]','App\Controller\Crud:get');
            $group->get('/getById/{table}/{id}','App\Controller\Crud:getById');
            $group->get('/getByField/{table}/{field}/{value}/{page}','App\Controller\Crud:getByField');
            $group->get('/getByLikeField/{table}/{field}/{value}','App\Controller\Crud:getByLikeField');
            
            $group->post('/add/{table}','App\Controller\Crud:add');
            $group->post('/update/{table}/{id}','App\Controller\Crud:update');
            $group->post('/delete/{table}/{id}','App\Controller\Crud:delete');
            $group->get('/getUsers/[{search}]','App\Controller\Crud:getUsers');
            $group->get('/getPurchases/{page}/[{search}]','App\Controller\Crud:getPurchases');
            $group->get('/getUserFavorites/{userId}/{listType}/{page}/[{search}]','App\Controller\Crud:getUserFavorites');
         
            $group->get('/getPackages/{countryId}/{discounted}/{page}/[{search}]','App\Controller\Crud:getPackages');

            
            //----
            $group->get('/getHome','App\Controller\Crud:getHome');
         
            $group->get('/listMessages/{page}','App\Controller\Crud:listMessages');
            $group->get('/listMessages2/{page}/[{search}]','App\Controller\Crud:listMessages');

            $group->get('/getMessageDetails/{id}','App\Controller\Crud:getMessageDetails');
           
            $group->get('/listSuccessStories/{page}','App\Controller\Crud:listSuccessStories');
            $group->post('/susbendUser','App\Controller\Crud:susbendUser');
            $group->post('/reActiveUser','App\Controller\Crud:reActiveUser');
            
            
            $group->post('/adminNotification','App\Controller\Crud:adminNotification');

            $group->post('/usersChart','App\Controller\Crud:usersChart');
            $group->post('/paymentsChart','App\Controller\Crud:paymentsChart');
            $group->post('/listUsers','App\Controller\Crud:listUsers');
            $group->post('/listPurchases','App\Controller\Crud:listPurchases');
            $group->post('/subscripePackage','App\Controller\Crud:subscripePackage');
            $group->get('/getUserChatList/{userId}','App\Controller\Crud:getUserChatList');
            $group->get('/getAllUsersChatList/{page}','App\Controller\Crud:getAllUsersChatList');
            $group->get('/openChat/{chatId}','App\Controller\Crud:openChat');

            $group->post('/addAgent','App\Controller\Crud:addAgent');
            $group->post('/updateAgent','App\Controller\Crud:updateAgent');
            $group->get('/listAgents','App\Controller\Crud:listAgents');

            
            $group->post('/addCopouns','App\Controller\Crud:addCopouns');
            $group->post('/agentLogin','App\Controller\Crud:agentLogin');
            $group->get('/listCopouns/{status}/{page}/[{agentId}]','App\Controller\Crud:listCopouns');
            $group->post('/assignCopoun','App\Controller\Crud:assignCopoun');
            $group->get('/getAgentDetails/{id}','App\Controller\Crud:getAgentDetails');
            $group->get('/deleteCopoun/{id}','App\Controller\Crud:deleteCopoun');
            $group->get('/deleteAllCopouns/{agentId}','App\Controller\Crud:deleteAllCopouns');

            $group->get('/deleteAgent/{id}','App\Controller\Crud:deleteAgent');
            $group->get('/listFailedPurchases/{page}','App\Controller\Crud:listFailedPurchases');
            $group->get('/getAdminPackages','App\Controller\Crud:getAdminPackages');

            $group->get('/deleteAdminNotifications','App\Controller\Crud:deleteAdminNotifications');
            $group->get('/readAllMessages','App\Controller\Crud:readAllMessages');
            $group->get('/unReadAllMessages','App\Controller\Crud:unReadAllMessages');
            $group->get('/listPendingPhotos/{page}','App\Controller\Crud:listPendingPhotos');
            $group->post('/confirmUploadPhoto','App\Controller\Crud:confirmUploadPhoto');
            $group->post('/refuseUploadPhoto','App\Controller\Crud:refuseUploadPhoto');

            $group->post('/sendGeneralMessage','App\Controller\Crud:sendGeneralMessage');
            
            $group->post('/addTelesales','App\Controller\Crud:addTelesales');
            $group->post('/updateTelesales','App\Controller\Crud:updateTelesales');
            
            $group->get('/deleteTelesales/{id}','App\Controller\Crud:deleteTelesales');
            $group->get('/getTelesalesDetails/{id}','App\Controller\Crud:getTelesalesDetails');

            
            $group->get('/listTelesales','App\Controller\Crud:listTelesales');
            $group->post('/telesalesLogin','App\Controller\Crud:telesalesLogin');

                   
            
        });

    });