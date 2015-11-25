<?php
/*
Plugin Name: GP social login
Plugin URI: https://github.com/gammapartners
Description: Log in to Wordpress using social services (Facebook, Google+)
Version: 1.0
Author: Rene Manqueros
Author URI: https://github.com/reneManqueros/
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

class gp_social_login{

    public static function createorlogin($email, $firstname, $lastname){
        if (null == username_exists($email)) {

            $password = uniqid() . date('Hms');
            $user_id = wp_create_user($email, $password, $email);

            wp_update_user(
                array(
                    'ID' => $user_id,
                    'first_name' => $firstname,
                    'last_name' => $lastname,
                    'nickname' => $firstname
                )
            );
        } else {
            $user_id = get_user_by('email', $email)->ID;

        }
        $user = new WP_User($user_id);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        $gp_options = get_option('gp_social_options');
        header("Location: " .  $gp_options['gp_social_redirect_on_login']);
    }

    public static function googlecreateurl($message){
        require_once 'Google/autoload.php';

        $gp_options = get_option('gp_social_options');

        $client = new Google_Client();
        $client->setClientId($gp_options['gp_social_google_client_id']);
        $client->setClientSecret($gp_options['gp_social_google_client_secret']);
        $client->setRedirectUri($gp_options['gp_social_google_redirect_uri']);
        $client->addScope("email");
        $client->addScope("profile");

        $authUrl = $client->createAuthUrl();
        return '<a class="gp_sociallogin google" href="' . $authUrl . '">' . $message . '</a>';
    }

    public static function googlecallback(){
        require_once 'Google/autoload.php';
        $gp_options = get_option('gp_social_options');
        $client = new Google_Client();
        $client->setClientId($gp_options['gp_social_google_client_id']);
        $client->setClientSecret($gp_options['gp_social_google_client_secret']);
        $client->setRedirectUri($gp_options['gp_social_google_redirect_uri']);
        $client->addScope("email");
        $client->addScope("profile");

        $service = new Google_Service_Oauth2($client);

        if (isset($_GET['code'])) {
            $client->authenticate($_GET['code']);
            $client->setAccessToken($client->getAccessToken());
            $user = $service->userinfo->get();

            $firstName = $user->givenName;
            $lastName = $user->familyName;
            $email = $user->email;
            gp_social_login::createorlogin($email, $firstName, $lastName);
            die();
        }
    }

    public static function facebookcreateurl($message){
        session_start();
        $gp_options = get_option('gp_social_options');
        $facebooksettings = [
            'app_id' => $gp_options['gp_social_facebook_app_id'],
            'app_secret' => $gp_options['gp_social_facebook_app_secret'],
            'default_graph_version' => 'v2.4',
        ];

        require_once 'Facebook/autoload.php';

        $fb = new Facebook\Facebook($facebooksettings);
        $helper = $fb->getRedirectLoginHelper();
        $permissions = ['email'];
        $loginUrl = $helper->getLoginUrl($gp_options['gp_social_facebook_redirect_url'], $permissions);
        return '<a class="gp_sociallogin facebook" href="' . htmlspecialchars($loginUrl) . '">' . $message . '</a>';
    }

    public static function facebookcallback(){
        session_start();
        date_default_timezone_set('America/Los_Angeles');
        require_once 'Facebook/autoload.php';

        $gp_options = get_option('gp_social_options');
        $facebooksettings = [
            'app_id' => $gp_options['gp_social_facebook_app_id'],
            'app_secret' => $gp_options['gp_social_facebook_app_secret'],
            'default_graph_version' => 'v2.4',
        ];

        $fb = new Facebook\Facebook($facebooksettings);
        $helper = $fb->getRedirectLoginHelper();
        try {
            $accessToken = $helper->getAccessToken();
        } catch(Facebook\Exceptions\FacebookResponseException $e) {
            echo 'Graph returned an error: ' . $e->getMessage();
            exit;
        } catch(Facebook\Exceptions\FacebookSDKException $e) {
            echo 'Facebook SDK returned an error: ' . $e->getMessage();
            exit;
        }
        if (isset($accessToken)) {
            $_SESSION['facebook_access_token'] = (string) $accessToken;
            try {
                $response = $fb->get('/me?fields=id,name,email,last_name,first_name', $accessToken);

            } catch(Facebook\Exceptions\FacebookResponseException $e) {
                echo 'Graph returned an error: ' . $e->getMessage();
                exit;
            } catch(Facebook\Exceptions\FacebookSDKException $e) {
                echo 'Facebook SDK returned an error: ' . $e->getMessage();
                exit;
            }

            $user = $response->getGraphUser();

            $firstName = $user->getFirstName();
            $lastName = $user->getLastName();
            $email = $user->getEmail();

            gp_social_login::createorlogin($email, $firstName, $lastName);
            die();
        }
    }
}

function gp_social_google($params){
    $message = 'Login with Google';
    if($params["message"] != ""){
        $message = $params["message"];
    }
    return gp_social_login::googlecreateurl($message);
}

function gp_social_facebook($params){
    $message = 'Login with Facebook';
    if($params["message"] != ""){
        $message = $params["message"];
    }
    return gp_social_login::facebookcreateurl($message);
}

add_action( 'wp', 'registerpluginmethods' );
function registerpluginmethods()
{
    if(preg_match('/^\/googlecallback\//' , $_SERVER['REQUEST_URI']) == true){
        gp_social_login::googlecallback();
        die();
    }

    if(preg_match('/^\/facebookcallback\//' , $_SERVER['REQUEST_URI']) == true){
        gp_social_login::facebookcallback();
        die();
    }
}

add_shortcode('gp_social_google', 'gp_social_google');
add_shortcode('gp_social_facebook', 'gp_social_facebook');


class gp_social_admin
{
    private $options;

    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    public function add_plugin_page()
    {
        add_options_page(
            'Settings Admin',
            'GP Social Login',
            'manage_options',
            'gp-social-login-admin',
            array( $this, 'create_admin_page' )
        );
    }

    public function create_admin_page()
    {
        $this->options = get_option( 'gp_social_options' );
        ?>
        <div class="wrap">
            <h2>GP Social Login</h2>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'gp_social_option_group' );
                do_settings_sections( 'my-setting-admin' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function page_init()
    {
        register_setting(
            'gp_social_option_group',
            'gp_social_options'

        );

        add_settings_section(
            'setting_section_id', 'GP Social Login Settings', array(  ), 'my-setting-admin'
        );

        add_settings_field('gp_social_google_client_id', 'Google Client ID', array( $this, 'gp_social_field_callback' ), 'my-setting-admin', 'setting_section_id', 'gp_social_google_client_id' );
        add_settings_field('gp_social_google_client_secret', 'Google Client Secret', array( $this, 'gp_social_field_callback' ), 'my-setting-admin', 'setting_section_id', 'gp_social_google_client_secret' );
        add_settings_field('gp_social_google_redirect_uri', 'Google Redirect URI', array( $this, 'gp_social_field_callback' ), 'my-setting-admin', 'setting_section_id', 'gp_social_google_redirect_uri' );

        add_settings_field('gp_social_facebook_redirect_url', 'Facebook redirect URL', array( $this, 'gp_social_field_callback' ), 'my-setting-admin', 'setting_section_id', 'gp_social_facebook_redirect_url' );
        add_settings_field('gp_social_facebook_app_id', 'Facebook App ID', array( $this, 'gp_social_field_callback' ), 'my-setting-admin', 'setting_section_id', 'gp_social_facebook_app_id' );
        add_settings_field('gp_social_facebook_app_secret', 'Facebook App secret', array( $this, 'gp_social_field_callback' ), 'my-setting-admin', 'setting_section_id', 'gp_social_facebook_app_secret' );

        add_settings_field('gp_social_redirect_on_login', 'Redirect on login', array( $this, 'gp_social_field_callback' ), 'my-setting-admin', 'setting_section_id', 'gp_social_redirect_on_login' );
    }

    public function gp_social_field_callback($field_id)
    {
        printf(
            '<input type="text" style="width:400px;" id="' . $field_id . '" name="gp_social_options[' . $field_id . ']" value="%s" />',
            isset( $this->options[$field_id] ) ? esc_attr( $this->options[$field_id]) : ''
        );
    }
}

if( is_admin() )
    $settings_page = new gp_social_admin();
