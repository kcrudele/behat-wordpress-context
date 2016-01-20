<?php

use Behat\Behat\Context\ClosuredContextInterface,
Behat\Behat\Context\TranslatedContextInterface,
Behat\Behat\Context\BehatContext,
Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
Behat\Gherkin\Node\TableNode;

use Behat\MinkExtension\Context\MinkContext;

/**
 * WordpressContext adds a set of human-readable WordPress-related commands to the Behat default set.
 *
 * @version 1.0
 * @author kfaulkner
 */
class WordpressContext extends MinkContext
{

    public function __construct()
    {
        $this->doc_root = __DIR__ . "/"; // set path to WordPress root
        require_once($this->doc_root . "wp-blog-header.php");
    }

     
    /**
     * Reset session when logging in is unnecessary
     * Resetting session helps stop failures due to browser form alert prompt 
     * 
     * @Given /^I have started a new session$/
     */
    public function iHaveStartedANewSession()
    {
        $this->getSession()->restart();
    }


    # --------------------------------------------------------------------------------- #
    #
    # BEGINNING THE SESSION
    #
    # --------------------------------------------------------------------------------- #
   
    /**
     * Log in to wordpress
     *
     * @param string $username
     * @param string $password
     * 
     * @Given /^I have logged in to wordpress with the username "([^"]*)" and the password "([^"]*)"$/
     */
    public function iHaveLoggedInWithTheUserAndThePassword($username, $password)
    {
        $this->getSession()->restart();
        $this->visit('/wp-admin');
        try {
            $this->fillField("user_login",$username);
            $this->fillField("user_pass",$password);
            $this->pressButton("wp-submit");
            
        } catch (Exception $e) {
            //return;
        }
        
        $this->assertPageContainsText("Howdy, " . $username);
    }


    /**
     * Log out of wordpress
     *
     * @Given /^I have logged out of wordpress$/
     */
    public function iHaveLoggedOutOfWordpress()
    {

        $session = $this->session = $this->getSession();
        $page = $session->getPage();

        $this->visit( 'wp-login.php?action=logout' );
        if ($session->getPage()->hasLink('log out')) {

        }
        $page->find('css', 'a')->click();

        $this->assertPageContainsText("Password");
    }

    # --------------------------------------------------------------------------------- #
    #
    # USER CREATION AND MODIFICATION
    #
    # --------------------------------------------------------------------------------- #

    
    /**
     * Creates a new user or updates an existing user in the WordPress database
     *
     * @param string $role accepts any default WordPress role ('subscriber', 'contributor', 'author', 'editor', 'administrator'),  or a custom defined role
     * @param string $username
     * @param string $password
     * 
     * @Given /^the "([^"]*)" user "([^"]*)" with the password "([^"]*)" exists$/
     */
    public function theUserWithThePasswordExists($role, $username, $password)
    {
    
        $user = get_user_by('login', $username);
        if(!$user) {
            $user_id = wp_create_user($username, $password, $username.'@test.com');
            $user = new WP_User( $user_id );
            wp_update_user(array(
                'ID'    => $user_id,
                'nickname'  => $username,
                'user_pass' => $password
            ));
        } 
        
        // set role of user
        if( null != get_role( $role ) ) {
              $user->set_role( $role );
        } else {
            throw new Exception("Role " . $role . " doesn't exist in Wordpress.");
        }
 
    }
    
    
    /**
     * Clears metadata within the specified user profile for a given meta key
     * 
     * @param string $meta_key
     * @param string $username
     * 
     * @Given /^the "([^"]*)" metadata for the user "([^"]*)" is not set$/
     */
    public function theMetadataForTheUserIsNotSet($meta_key, $username)
    {
        $user = get_user_by( 'login', $username );
         
         if(!$user) {
            throw new Exception("User " . $user . " doesn't exist.");
         } else {
            delete_user_meta($user->ID, $meta_key);
         }
    }

    # --------------------------------------------------------------------------------- #
    #
    # RESOURCES
    #
    # --------------------------------------------------------------------------------- #


    /**
     * Upload images into the media library for later use
     * Requires the @javascript directive in front of the scenario
     * 
     * @param string $filename expects files for upload to reside in the resources folder
     * 
     * @Given /^I have uploaded the image "([^"]*)" into the media library$/
     */
    public function uploadImageToMediaLibrary($file_name)
    {
        $path_parts = pathinfo($file_name);
        $page = get_page_by_title( $path_parts['filename'], "ARRAY_A", "attachment");

        if ($page == null) {
            $resources = __DIR__ . "\\..\\..\\resources\\";
            $dir = dir($resources);
            $filePath = $dir->path . $file_name;

            $this->visit("/wp-admin/media-new.php");
            $this->attachFileToField("async-upload",$filePath);
            $this->pressButton("Upload");
            $this->getSession()->wait(1000);
            $path_parts = pathinfo($file_name);
            $image = get_page_by_title( $path_parts['filename'], "ARRAY_A", "attachment");
            if($image != null && isset($image['ID'])) {
                update_post_meta( $image['ID'], "_wp_attachment_image_alt",  $path_parts['filename']);
            }
            $this->assert($image != null,"Media could not be uploaded.");
        }
    }


    # --------------------------------------------------------------------------------- #
    #
    # THEME SETUP
    #
    # --------------------------------------------------------------------------------- #

    /**
     * Activate a theme
     * 
     * @param string $theme_name the name as it is defined in the theme's stylesheet file
     * @Given /^I activate the theme "([^"]*)"$/
     */
    public function iAmUsingTheTheme($theme_name)
    {
        switch_theme( $theme_name );
    }

    /**
     * Change the permalink structure
     * 
     * @param string $permalink_structure
     * 
     * @Given /^I have changed the permalink structure to "([^"]*)"$/
     */
    public function iHaveChangedThePermalinkTo($permalink_structure)
    {
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure($permalink_structure);
        $wp_rewrite->flush_rules();
    }

    /**
     * Creates a new menu within the WordPress admin
     * 
     * @param string $menu_name
     * 
     * @Given /^I create a menu named "([^"]*)"$/
     */
    public function createMenuName($menu_name) {
        if (wp_get_nav_menu_object($menu_name) === false) {
            wp_create_nav_menu($menu_name);

        }
        $this->assert(wp_get_nav_menu_object($menu_name) !== false, "Menu was not created");
    }

    /**
     * Assign the menu list to a theme location
     * 
     * @param string $menu the name of the menu as it was defined during menu creation
     * @param string $location the name of the location as it was defined in the register_nav_menu method
     *
     * @Given /^I set the menu "([^"]*)" to the theme location "([^"]*)"$/
     */
    public function iSetTheMenuToTheThemeLocation($menu, $location)
    {
        $menu = wp_get_nav_menu_object($menu);
        $locations = get_theme_mod('nav_menu_locations');
        $locations[$location] = $menu->term_id;
        set_theme_mod( 'nav_menu_locations', $locations );
    }

    /**
     * Creates a new menu item within a given menu list
     *
     * @param string $menu_item
     * @param string $menu
     * @param string $slug_or_url
     *
     * @Given /^I add a new menu item "([^"]*)" to the "([^"]*)" menu that links to "([^"]*)"$/
     */
    public function iAddANewMenuItemToTheMenuThatGoesTo($menu_item, $menu, $slug_or_url)
    {
        $menu = wp_get_nav_menu_object($menu);
        $this->assert($menu !== false, "Menu does not exist");
        // Set up default menu items
        wp_update_nav_menu_item($menu->term_id, 0, array(
            'menu-item-title' =>  __($menu_item),
            'menu-item-url' => home_url($slug_or_url),
            'menu-item-status' => 'publish'));

    }
    

    /**
     * Change the page that is marked as the Front Page
     * 
     * @param string $title title of the page
     * 
     * @Given /^I set the front page to "([^"]*)"$/
     */
    public function iSetTheFrontPageTo($title)
    {
        $page = get_page_by_title($title);
        if ($page == null) {
            throw new Exception("Page does not exist.");
        } else {
            update_option('page_on_front', $page->ID);
            update_option('show_on_front', 'page');
        }
    }


    /**
     * Update settings in the theme options customizer API
     * 
     * @param string $key
     * @param string $value
     * 
     * @Given /^the theme option "([^"]*)" is set to "([^"]*)"$/
     */
    public function theThemeSettingIsSetTo($key, $value)
    {
        update_option($key, $value);
    }


    /**
     * Add a tag to a given taxonomy
     * 
     * @param string $taxonomy the name of the taxonomy as it was registered in the register_taxonomy method
     * @param string $term
     * 
     * @Given /^the "([^"]*)" taxonomy has the term "([^"]*)"$/
     */
    public function theTaxonomyHasTheTerm($taxonomy, $term)
    {
        if(null === get_term($term, $taxonomy)) {
            wp_insert_term( $term, $taxonomy);
        }

    }


    # --------------------------------------------------------------------------------- #
    #
    # POST CREATION AND EDITING
    #
    # --------------------------------------------------------------------------------- #


    /**
     * Create a post of any type (post, page, or any predefined custom post type) and assigns it a title and a slug in order to reference it for later use
     * 
     * @param string $type any default WordPress post type ('post', 'page', 'attachment', etc.) or predefined custom post type
     * @param string $title
     * @param string $slug
     * 
     * @Given /^I have an? "([^"]*)" item with the title "([^"]*)" and the slug "([^"]*)"$/
     */
    public function AssertCreatedPost($type, $title, $slug) {
        $targetPost = get_page_by_title( $title, "ARRAY_A", $type);
        if ($targetPost == null) {
            $a = array('post_name'   => $slug,'post_title' => $title,'post_status'   => 'publish','post_type'   => $type, 'post_content' => "");
            $id = wp_insert_post($a);
            $this->assert($id > 0, "Post was not successfully created.");
  
        } else {
            $targetPost["post_name"] = $slug;
            $targetPost["post_status"] = 'publish';
            $targetPost["post_content"] = "";
            $id = wp_update_post($targetPost);
            $this->assert($id > 0, "Post was not successfully updated. Post does not exist.");

        }
    }

    
    /**
     * Add excerpt content to any post created in the WordPress database
     *
     * @param string $type any default WordPress post type ('post', 'page', 'attachment', etc.) or predefined custom post type
     * @param string $title
     * @param string $excerpt
     * 
     * @Given /^the "([^"]*)" item titled "([^"]*)" has the excerpt "([^"]*)"$/
     */
    public function theItemHasTheExcerpt($type, $title, $excerpt)
    {
        $targetPost = get_page_by_title( $title, "ARRAY_A", $type);
        if ($targetPost == null) {
            throw new Exception("Post does not exist.");
        } else {
            $targetPost["post_excerpt"] = $excerpt;
            $id = wp_update_post($targetPost);
            $this->assert($id > 0, "Excerpt was not successfully updated. Post does not exist.");         
        }
    }
    
    
    /**
     * Add author content to any blog post created in the WordPress database
     * 
     * @param string $type any default WordPress post type ('post', 'page', 'attachment', etc.) or predefined custom post type
     * @param string $title
     * @param string $author any username predefined in the database
     * 
     * @Given /^the "([^"]*)" item titled "([^"]*)" has the author "([^"]*)"$/
     */
    public function theItemHasTheAuthor($type, $title, $author)
    {
        $targetPost = get_page_by_title( $title, "ARRAY_A", $type);
        
        $user = get_user_by("login", $author);
        
        if(!isset($user) || !isset($user->ID)) {
            throw new Exception("Can't find user with that login.");
        } else {
        
            if ($targetPost != null) {
                $targetPost["post_author"] = $user->ID;
                $id = wp_update_post($targetPost);
                $this->assert($id > 0, "Author was not successfully updated. Post does not exist.");            
            } else {
                throw new Exception("Can't find post with that name.");
            }
        
        }

    }
    
    
    /**
     * Add content to the content editor for any post created in the WordPress database
     * 
     * @param string $type any default WordPress post type ('post', 'page', 'attachment', etc.) or predefined custom post type
     * @param string $title
     * @param string $content
     * 
     * @Given /^the "([^"]*)" item titled "([^"]*)" has the content "([^"]*)"$/
     */    
    public function AssertPostContent($type, $title, $content) {
        $targetPost = get_page_by_title( $title, "ARRAY_A", $type);
        $this->assert($targetPost != null, "Post content could not be modified");
        $targetPost["post_content"] = $content;
        $id = wp_update_post($targetPost);
        $this->assert($id > 0, "Post content could not be modified");
    }


    /**
     * Add a custom field for any post created in the WordPress database
     *
     * @param string $type any default WordPress post type ('post', 'page', 'attachment', etc.) or predefined custom post type
     * @param string $title
     * @param string $custom_key
     * @param string $custom_value
     * 
     * @Given /^the "([^"]*)" item titled "([^"]*)" has the custom field "([^"]*)" with the value "([^"]*)"$/
     */
    public function theItemHasTheCustomField($type, $title, $custom_key, $custom_value)
    {
        $targetPost = get_page_by_title( $title, "ARRAY_A", $type);

        if ($targetPost == null) {
            throw new Exception("Post does not exist.");
        } else {
            $id = add_post_meta( $targetPost['ID'], $custom_key, $custom_value );
            $this->assert($id > 0, "Custom field was not successfully updated. Post does not exist.");
        }

    }


    /**
     * Delete any post created in the WordPress database
     *
     * @param string $type any default WordPress post type ('post', 'page', 'attachment', etc.) or predefined custom post type
     * @param string $title
     * 
     * @Given /^I delete the "([^"]*)" item titled "([^"]*)"$/
     */
    public function iDeleteThePostTitled($type, $title)
    {
        $thePost = get_page_by_title( $title, "ARRAY_A", $type);
        wp_delete_post($thePost["ID"], true);

        $deletedPost = get_page_by_title( $title, "ARRAY_A", $type);
        $this->assert($deletedPost == null, "Post was not successfully deleted");
 
    }

    /**
     * Physically sets the active browser page to the post editor in the WordPress admin tool.
     * Useful for situations where custom metaboxes or javascript functionality need to be tested.
     *
     * @param string $type
     * @param string $title
     * 
     * @When /^I am editing the "([^"]*)" item titled "([^"]*)"$/
     */
    public function iEditThePostTitled($type, $title)
    {
        $targetPost = get_page_by_title( $title, "ARRAY_A", $type);

        if ($targetPost == null) {
            throw new Exception("Post does not exist.");
        } else if($targetPost['post_status'] == "trash") {
            throw new Exception("You can't edit this item because it is in the Trash. Please restore it and try again.");
        }else  {
            $page_id = $targetPost['ID'];
            $this->visit('/wp-admin/post.php?post=' . $page_id . '&action=edit');
        }
    }

    /**
     * Add thumbnail image
     * 
     * @param string $type
     * @param string $title
     * @param string $image the same name as what is uploaded into the media library. Name should exclude file extension.
     * 
     * @Given /^the "([^"]*)" item titled "([^"]*)" has the featured image set to "([^"]*)"$/
     */
    public function theHasTheFeaturedImageSetTo($type, $title, $image)
    {
        $targetPost = get_page_by_title( $title, "ARRAY_A", $type);

        $media = get_page_by_title( $image, "ARRAY_A", 'attachment');

        if(null != $media) {
            add_post_meta($targetPost['ID'], '_thumbnail_id', $media['ID']);

        } else {
            throw new Exception("Image " . $image . " does not exist.");
        }

    }


    /**
     * Replace the taxonomy terms for an individual post item or clear them completely
     * 
     * @param string $type
     * @param string $title
     * @param string $taxonomy the name as defined in the register_taxonomy method
     * @param string $term to clear or remove all terms, pass an empty string
     * 
     * @Given /^the "([^"]*)" item titled "([^"]*)" has the taxonomy "([^"]*)" set to "([^"]*)"$/
     */
    public function theItemHasTheTaxonomySetTo($type, $title, $taxonomy, $term)
    {
        $targetPost = get_page_by_title( $title, "ARRAY_A", $type);

        if ($targetPost != null) {
            wp_set_object_terms( $targetPost["ID"], array($term), $taxonomy );
        } else {
            throw new Exception("Cannot set meta data on post that does not exist.");
        }

    }


    /**
     * Append a term from a given taxonomy for an individual post item
     * 
     * @param string $type
     * @param string $title
     * @param string $term
     * @param string $taxonomy the name as defined in the register_taxonomy method
     * 
     * @Given /^the "([^"]*)" item titled "([^"]*)" appends the term "([^"]*)" to the taxonomy "([^"]*)"$/
     */
    public function theItemAppendsTheTerm($type, $title, $term, $taxonomy)
    {
        $targetPost = get_page_by_title( $title, "ARRAY_A", $type);

        if ($targetPost != null) {
            wp_set_object_terms( $targetPost["ID"], array($term), $taxonomy , true);
        } else {
            throw new Exception("Cannot set meta data on post that does not exist.");
        }

    }


    /**
     * Confirm that the publish date appears correctly within the post page
     *
     * @param string $type
     * @param string $title
     * @param string $selector
     *
     * @Given /^I should see the publish date for the "([^"]*)" item titled "([^"]*)" in the "([^"]*)" element$/
     */
    public function iShouldSeeThePublishDateForInTheElement($type, $title, $selector)
    {
        $session = $this->getSession();
        $element = $session->getPage()->find('css', $selector);

        // errors must not pass silently
        if (null === $element) {
            throw new \InvalidArgumentException(sprintf('Could not evaluate CSS selector: "%s"', $selector));
        }

        $post = get_page_by_title( $title, "ARRAY_A", $type);
        $targetDate = get_the_date("F j, Y", $post['ID']);

        if(strpos($element->getText(), $targetDate) === FALSE) {
            throw new \LogicException(sprintf('Could not find the current date in the element: "%s"', $selector));
        }
    }

    # --------------------------------------------------------------------------------- #
    #
    # CUSTOM METABOX DATA SETTING
    #
    # --------------------------------------------------------------------------------- #


    /**
     * Remove meta data assigned to a given key for an individual post item
     * 
     * @param string $meta_key
     * @param string $type
     * @param string $title
     * 
     * @Given /^the "([^"]*)" meta data for the "([^"]*)" item titled "([^"]*)" is reset$/
     */
    public function assertMetaDataMissing($meta_key,$type, $title)
    {
        $targetPost = get_page_by_title( $title, "ARRAY_A", $type);
        if ($targetPost != null) {
            delete_post_meta($targetPost["ID"],$meta_key);
            $val = get_post_meta($targetPost["ID"],$meta_key, true);
            $this->assert($val == "","Post Meta not removed");
        }
       
    }
    
    /**
     * Set meta values within metaboxes for an individual post item
     * 
     * @param string $type
     * @param string $title
     * @param string $meta_box
     * @param string $meta_key
     * @param string $meta_value
     * 
     * @Given /^the "([^"]*)" item titled "([^"]*)" has the "([^"]*)" metabox with the "([^"]*)" meta key set to "((?:[^\\"]|\\.)*)"$/
     */
    public function assertCustomMetaData($type, $title, $meta_box, $meta_key, $meta_value)
    {
        $targetPost = get_page_by_title( $title, "ARRAY_A", $type);
        if ($targetPost != null) {
        
            $content_type_meta = get_post_meta($targetPost["ID"], $meta_box, true);
            if($content_type_meta) {
                $content_type_meta[$meta_key] = $meta_value;
                update_post_meta($targetPost["ID"], $meta_box, $content_type_meta);
            } else {
                add_post_meta($targetPost["ID"], $meta_box, array($meta_key => $meta_value), true);
            }
                
            $val = get_post_meta($targetPost["ID"],$meta_box, true);
            $this->assert($val != "","Post Meta not added");
            
        } else {
            throw new Exception("Cannot set meta data on post that does not exist.");
        }
        
    }
        

    /**
     * Set or remove custom metadata in the user profile
     * 
     * @param string $username
     * @param string $meta_key
     * @param string $meta_value
     * 
     * @Given /^the "([^"]*)" user has the "([^"]*)" meta key set to "([^"]*)"$/
     */
    public function theMetadataForTheUserIsSetTo($username, $meta_key, $meta_value)
    {
         $user = get_user_by('login', $username);
         if(!$user) {
             throw new Exception("Username " . $username . " does not exist.");
         } else {
             update_user_meta($user->ID, $meta_key, $meta_value);
         }
    }

}
