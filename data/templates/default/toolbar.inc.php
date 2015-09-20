<?php
if ($userservice->isLoggedOn() && is_object($currentUser)) {
    $cUserId = $userservice->getCurrentUserId();
    $cUsername = $currentUser->getUsername();
?>

    <ul id="navigation">
    	<li><a href="<?php echo createURL(''); ?>"><?php echo T_('Home'); ?></a></li>    
        <li><a href="<?php echo createURL('bookmarks', $cUsername); ?>"><?php echo T_('Bookmarks'); ?></a></li>
	<li><a href="<?php echo createURL('alltags', $cUsername); ?>"><?php echo T_('Tags'); ?></a></li>
        <li><a href="<?php echo createURL('watchlist', $cUsername); ?>"><?php echo T_('Watchlist'); ?></a></li>
	<li><a href="<?php echo $userservice->getProfileUrl($cUserId, $cUsername); ?>"><?php echo T_('Profile'); ?></a></li>
        <li><a href="<?php echo createURL('bookmarks', $cUsername . '?action=add'); ?>"><?php echo T_('Add a Bookmark'); ?></a></li>

<?php if (isset($loadjs)) :?>
        <li class="access"><button type="button" id="button" title="shoulder surfing protection" onclick="if(! $.cookie('noshoulderSurfingProtection')) {toggle();} else {$.removeCookie('noshoulderSurfingProtection', { path: '/' }); location.reload();}"><?php if(! isset($_COOKIE["noshoulderSurfingProtection"])) {echo "Protected";} else {echo "Unprotected";} ?></button></li>
<?php endif ?>

        <li class="access"><?php echo $cUsername?><a href="<?php echo ROOT ?>?action=logout">(<?php echo T_('Log Out'); ?>)</a></li>
        <li><a href="<?php echo createURL('about'); ?>"><?php echo T_('About'); ?></a></li>
	<?php if($currentUser->isAdmin()): ?>
        <li><a href="<?php echo createURL('admin', ''); ?>"><?php echo '['.T_('Admin').']'; ?></a></li>
	<?php endif; ?>
    </ul>

<?php if (isset($loadjs)) :?>
    <div id="password-form" style="background:white; z-index: 2; position:absolute; top:55px; right:10px; visibility:hidden;">
        <form id="noshoulderSurfingProtectionPassword">
                  <input type="password" name="password" id="password" size="40" placeholder="Type your password then press Enter to unprotect."/>
                  <!-- Allow form submission with keyboard without duplicating the dialog button -->
                  <input type="submit" tabindex="-1" style="position:absolute; top:-1000px"/>
        </form>
    </div>
    <script>
        // Prevents browser autocompletion. autocomplete="off" as input type="password" attribute only works with HTML5.
        setTimeout(
            clear(),
            1000  //1,000 milliseconds = 1 second
        ); 
        function clear() {
            $('#password').val('');
        }
        function toggle() {
                if ($("#password-form").css("visibility") == "visible") {
                        $("#password-form").css("visibility", "hidden");
                }
                else {
                        clear();
                        $("#password-form").css("visibility", "visible");
                }
        }
        $( "#noshoulderSurfingProtectionPassword" ).submit(function( event ) {
                $.post(
                        '<?php echo ROOT ?>ajax/checkpassword.php',
                        {
                                password : $("#password").val(),
                        },
                        function(data) {
                                if(data == 'true') {
                                    $.cookie('noshoulderSurfingProtection', 'null', { path: '/' });
                                    location.reload();
                                }
                        },
                        'text'
                );
                event.preventDefault();
        }); 
    </script>
<?php endif ?>
<?php
} else {
?>
    <ul id="navigation">
    	<li><a href="<?php echo createURL(''); ?>"><?php echo T_('Home'); ?></a></li>
	<li><a href="<?php echo createURL('populartags'); ?>"><?php echo T_('Popular Tags'); ?></a></li>
        <li><a href="<?php echo createURL('about'); ?>"><?php echo T_('About'); ?></a></li>
        <li class="access"><a href="<?php echo createURL('login'); ?>"><?php echo T_('Log In'); ?></a></li>
        <?php if ($GLOBALS['enableRegistration']) { ?>
        <li class="access"><a href="<?php echo createURL('register'); ?>"><?php echo T_('Register'); ?></a></li>
        <?php } ?>
    </ul>

<?php
}
?>
