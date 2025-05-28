<?php
/*
    * Cart page - Shortcut Flow.
*/
    session_start();
    $rootPath = "";
    include_once('api/Config/Config.php');
    include_once('api/Config/Sample.php');
    include('templates/header.php');

    $baseUrl = str_replace("index.php", "", URL['current']);
?>

<!-- HTML Content -->
<div class="row-fluid">
    <!-- Left Section -->
    <div class="col-md-3" id="leftSection">
        <div class="card">
            <img class="card-img-top img-responsive" src="<?= $rootPath ?>img/camera.jpg">
            <div class="card-body">
                <h4 class="text-center">Sample Sandbox Buyer Credentials</h4>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col">Buyer Email</th>
                            <th scope="col">Password</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>emily_doe@buyer.com</td>
                            <td>qwer1234</td>
                        </tr>
                        <tr>
                            <td>bill_bong@buyer.com</td>
                            <td>qwer1234</td>
                        </tr>
                        <tr>
                            <td>jack_potter@buyer.com</td>
                            <td>123456789</td>
                        </tr>
                        <tr>
                            <td>harry_doe@buyer.com</td>
                            <td>123456789</td>
                        </tr>
                        <tr>
                            <td>ron_brown@buyer.com</td>
                            <td>qwer1234</td>
                        </tr>
                        <tr>
                            <td>bella_brown@buyer.com</td>
                            <td>qwer1234</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Middle Section -->
    <div class="col-md-4">
        <h3 class="text-center">Pricing Details.......</h3>
        <hr>
        <form class="form-horizontal">
            <!-- Cart Details -->
            <div class="form-group">
                <label for="camera_amount" class="col-sm-5 control-label">Camera</label>
                <div class="col-sm-7">
                    <input class="form-control"
                           type="text"
                           id="camera_amount"
                           name="camera_amount"
                           value="<?= SampleCart['item_amt'] ?>"
                           readonly>
                </div>
            </div>
            <div class="form-group">
                <label for="tax_amt" class="col-sm-5 control-label">Tax</label>
                <div class="col-sm-7">
                    <input class="form-control"
                           type="text"
                           id="tax_amt"
                           name="tax_amt"
                           value="<?= SampleCart['tax_amt'] ?>"
                           readonly>
                </div>
            </div>
            <div class="form-group">
                <label for="insurance_fee" class="col-sm-5 control-label">Insurance</label>
                <div class="col-sm-7">
                    <input class="form-control"
                           type="text"
                           id="insurance_fee"
                           name="insurance_fee"
                           value="<?= SampleCart['insurance_fee'] ?>"
                           readonly>
                </div>
            </div>
            <div class="form-group">
                <label for="handling_fee" class="col-sm-5 control-label">Handling Fee</label>
                <div class="col-sm-7">
                    <input class="form-control"
                           type="text"
                           id="handling_fee"
                           name="handling_fee"
                           value="<?= SampleCart['handling_fee'] ?>"
                           readonly>
                </div>
            </div>
            <div class="form-group">
                <label for="shipping_amt" class="col-sm-5 control-label">Estimated Shipping</label>
                <div class="col-sm-7">
                    <input class="form-control"
                           type="text"
                           id="shipping_amt"
                           name="shipping_amt"
                           value="<?= SampleCart['shipping_amt'] ?>"
                           readonly>
                </div>
            </div>
            <div class="form-group">
                <label for="shipping_discount" class="col-sm-5 control-label">Shipping Discount</label>
                <div class="col-sm-7">
                    <input class="form-control"
                           type="text"
                           id="shipping_discount"
                           name="shipping_discount"
                           value="<?= SampleCart['shipping_discount'] ?>"
                           readonly>
                </div>
            </div>
            <div class="form-group">
                <label for="total_amt" class="col-sm-5 control-label">Total Amount</label>
                <div class="col-sm-7">
                    <input class="form-control"
                           type="text"
                           id="total_amt"
                           name="total_amt"
                           value="<?= SampleCart['total_amt'] ?>"
                           readonly>
                </div>
            </div>
            <div class="form-group">
                <label for="currency_Code" class="col-sm-5 control-label">Currency</label>
                <div class="col-sm-7">
                    <input class="form-control"
                           type="text"
                           id="currency_Code"
                           name="currency_Code"
                           value="USD"
                           readonly>
                </div>
            </div>
            <hr>

            <!-- Checkout Options -->
            <div class="form-group">
                <div class="col-sm-offset-5 col-sm-7">
                    <!-- Container for PayPal Shortcut Checkout -->
                    <div id="paypalCheckoutContainer"></div>

                    <!-- Container for PayPal Mark Redirect -->
                    <div id="paypalMarkRedirect">
                        <h4 class="text-center">OR</h4>
                        <a class="btn btn-success btn-block" href="<?= $rootPath ?>pages/shipping.php" role="button">
                            <h4>Proceed to Checkout</h4>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Right Section -->
    <div class="col-md-5" id="rightSection">
        <h3 class="text-center">Readme</h3>
        <ol>
            <li>
                Enter REST API credentials in api/Config/Config.php. You can get your own REST app credentials by creating a REST app with the steps outlined
                <i>
                    <a href="https://developer.paypal.com/docs/integration/direct/make-your-first-call/#create-a-paypal-app" target="_blank">here</a>
                </i>.
            </li>
            <li>
                Click on 'PayPal Checkout’ button and see the experience.
            </li>
            <li>
                If you get any Firewall warning, add rule to the Firewall to allow incoming connections for your application.
            </li>
            <li>
                Checkout with PayPal using a buyer sandbox account provided on this page. And you're done!
            </li>
            <li>In the guest checkout experience, the buyer country can be switched. When switched to one of Germany,Poland, Austria, Belgium, Netherlands, Italy and Spain, you will be able to choose the alternative payment methods offered in those countries. The shipping address will be pre-filled on the Shipping Information page for these countries. For all other countries, the address has to be manually entered.
            </li>
        </ol>
        <hr>
        <h3 class="text-center">In-Context Checkout integration steps with PayPal JavaScript SDK</h3>
        <ol>
            <li>
                Copy the files and folders in the package to the same location where you have your shopping cart page.
            </li>
            <li>
            In order to view Alternative Payment Methods as part of the guest checkout flow, you must add query parameters intent=capture, commit=true, vault=false and buyer-country= and you must provide a supported buyer country
            </li>
            <li>
                Include the following script on your shopping cart page: (For APMs, the layout must be <code>vertical</code> and setting up the payment in the alternative payment method <a href="https://developer.paypal.com/docs/checkout/integration-features/alternative-payment-methods/#availability" target="_blank">supported currency</a> is required for the alternative payment method to render.)
            <pre>
<code>paypal.Buttons({
    env: 'sandbox', // sandbox | production

        // Set style of buttons
        style: {
            layout: 'vertical',   // horizontal | vertical <-Must be vertical for APMs
            size:   'responsive',   // medium | large | responsive
            shape:  'pill',         // pill | rect
            color:  'gold',         // gold | blue | silver | black,
            fundingicons: false,    // true | false,
            tagline: false          // true | false,
        },

    // payment() is called when the button is clicked
    createOrder: function() {

        return fetch('/my-server/create-paypal-transaction')
            .then(function(res) {
                return res.json();
            }).then(function(data) {
                return data.orderID;
            });
    },

    // onAuthorize() is called when the buyer approves the payment
    onApprove: function(data, actions) {

        return fetch('/my-server/capture-paypal-transaction', {
                body: JSON.stringify({
                orderID: data.orderID
                })
            }).then(function(res) {
                return res.json();
            }).then(function(details) {
                alert('Transaction funds captured from ' + details.payer_given_name);
            });
    }
}).render('#paypal-button-container');</code>
            </pre>
            </li>
            <li>
                Open your browser and navigate to your Shopping cart page. Click on 'Checkout with PayPal' button and complete the flow.
            </li>
            <li>
                You can use the sample Buyer Sandbox credentials provided on index.php/home page.
            </li>
            <li>Refer to <a href="https://developer.paypal.com/docs/checkout/" target="_blank">PayPal Developer</a> site for detailed guidelines.</li>
            <li>Click <a href="https://developer.paypal.com/docs/api/orders/v2/" target="_blank">here</a> for the API reference.
        </ol>
    </div>
</div>
<!-- Javascript Import -->
<script src="https://www.paypal.com/sdk/js?client-id=sb&intent=capture&vault=false&commit=true<?php echo isset($_GET['buyer-country']) ? "&buyer-country=" . $_GET['buyer-country'] : "" ?>"></script>
<script src="<?= $rootPath ?>js/config.js"></script>

<!-- PayPal In-Context Checkout script -->
<script type="text/javascript">

    paypal.Buttons({

        // Set your environment
        env: '<?= PAYPAL_ENVIRONMENT ?>',

        // Set style of buttons
        style: {
            layout: 'vertical',   // horizontal | vertical
            size:   'responsive',   // medium | large | responsive
            shape:  'pill',         // pill | rect
            color:  'gold',         // gold | blue | silver | black,
            fundingicons: false,    // true | false,
            tagline: false          // true | false,
        },

        // Wait for the PayPal button to be clicked
        createOrder: function() {
            let formData = new FormData();
            formData.append('item_amt', document.getElementById("camera_amount").value);
            formData.append('tax_amt', document.getElementById("tax_amt").value);
            formData.append('handling_fee', document.getElementById("handling_fee").value);
            formData.append('insurance_fee', document.getElementById("insurance_fee").value);
            formData.append('shipping_amt', document.getElementById("shipping_amt").value);
            formData.append('shipping_discount', document.getElementById("shipping_discount").value);
            formData.append('total_amt', document.getElementById("total_amt").value);
            formData.append('currency', document.getElementById("currency_Code").value);
            formData.append('return_url',  '<?= $baseUrl.URL["redirectUrls"]["returnUrl"]?>' + '?commit=true');
            formData.append('cancel_url', '<?= $baseUrl.URL["redirectUrls"]["cancelUrl"]?>');

            return fetch(
                '<?= $rootPath.URL['services']['orderCreate']?>',
                {
                    method: 'POST',
                    body: formData
                }
            ).then(function(response) {
                return response.json();
            }).then(function(resJson) {
                console.log('Order ID: '+ resJson.data.id);
                return resJson.data.id;
            });
        },

        // Wait for the payment to be authorized by the customer
        onApprove: function(data, actions) {
            return fetch(
                '<?= $rootPath.URL['services']['orderGet'] ?>',
                {
                    method: 'GET'
                }
            ).then(function(res) {
                return res.json();
            }).then(function(res) {
                window.location.href = 'pages/success.php';
            });
        }

    }).render('#paypalCheckoutContainer');

</script>