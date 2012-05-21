<?php
/**
 * Plugin Name: Secure Categories
 * Plugin URI: http://secure-categories.chinga.us/
 * Description: Now you can protect your categories with password muahahaha
 * Version: 0.1
 * Author: Mario Alvarez
 * Author URI: http://marioalva.info/
 * License: WTFPL
 *
 * Secure Categories
 * It's very simple
 * We store option in relationship with secured categories, and when
 * the page renders, it will be evaluated; if is secured and doesn't have
 * any user login, redirect them to login page
 *
 * This program is free software. It comes without any warranty, to
 * the extent permitted by applicable law. You can redistribute it
 * and/or modify it under the terms of the Do What The Fuck You Want
 * To Public License, Version 2, as published by Sam Hocevar.
 * @see http://en.wikipedia.org/wiki/WTFPL
 *
 * @todo add field to category quick edit menu
 * @todo add configs page
 * @todo allow user to configure some operations like:
 * - Choose to use wp-login or custom page
 * - Choose to show admin-bar to subscribers
 * @todo Multilang
 */

/**
 * Launch only in public
 */
if ( ! is_admin())
	new SecureCategories;


class SecureCategories
{

	/**
	 * Constructor
	 * Hooks to WP actions
	 *
	 * @return void
	 */
	public function SecureCategories()
	{
	    /**
	     * Hook to categories lisst
	     */
	    add_action('manage_edit-category_columns', array($this, 'add_column'));
	    add_action('manage_category_custom_column', array($this, 'manage_column'), 10, 3);

	    /**
	     * Hook to add new category
	     */
	    add_action('category_add_form_fields', array($this, 'add_category_form_fields'));
	    add_action('create_category', array($this, 'create_category'));

	    /**
	     * Hook to edit category
	     */
	    add_action('edit_category_form_fields', array($this, 'edit_category_form_fields'));
	    add_action('edit_category', array($this, 'edit_category'));

	    /**
	     * Hook to render ;D
	     */
	    add_action('wp', array($this, 'hola'));

	}


	/**
	 * Add column to admin categories list
	 *
	 * @return string
	 */
	public function add_column ($cols)
	{
		$cols['is_secure'] = 'Secured';
		return $cols;
	}

	/**
	 * Add data to the new column on admin categories list
	 * It shows if category if secured or not
	 *
	 * @return void
	 */
	public function manage_column ($nada, $nothing, $id)
	{
		echo (bool) get_option(self::option_string(get_term_by('id', $id, 'category')->name)) ? 'yes' : 'no';
	}


	/**
	 * Add field to create category form
	 *
	 * @return void
	 */
	public function add_category_form_fields ($tag)
	{

		$is_secure = (bool) get_option(self::option_string($tag->name));

		?>
		<div class="form-field">
			<label for="is_secure"><input style="width: 14px;" value="1" type="checkbox" name="is_secure" id="is_secure" <?php echo $is_secure ? 'checked="checked"' : null ?>> Require login</label>
			<p>Login is needed to browse this category.</p>
		</div>
		<?php
	}

	/**
	 * Save from create category
	 *
	 * @return void
	 */
	public function create_category ()
	{
		update_option(self::option_string($_POST['tag-name']), isset($_POST['is_secure']));
	}


	/**
	 * Add field to edit category form
	 *
	 * @return void
	 */
	public function edit_category_form_fields ($tag)
	{
		$is_secure = (bool) get_option(self::option_string($tag->name));

		?>
		<tr class="form-field">
			<th scope="row" valign="top">
				<label for="is_secure">Require login</label>
			</th>
			<td>
				<input style="width: 14px;" value="1" type="checkbox" name="is_secure" id="is_secure" <?php echo $is_secure ? 'checked="checked"' : null ?>>
				<br>
				<span class="description">Login is needed to browse this category.</span>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save from edit category
	 *
	 * @return void
	 */
	public function edit_category ()
	{
		update_option(self::option_string($_POST['name']), isset($_POST['is_secure']));
	}


	/**
	 * WP hacked!
	 * Just before request finishes, we determine is the current
	 * scenario (category or single), is marked as secure, and if it is,
	 * just redirect visitor to wp login using current url as argument
	 * to the visitor return when login finishes
	 *
	 * @return void
	 */
	public function hola ()
	{

		/**
		 * If current category isn't secured we doesn'y have anything to do here
		 */
		if ( ! self::is_secured())
			return;

		/**
		 * Check if the current viisitor is logged in
		 */
		if ( ! is_user_logged_in())
			wp_redirect('/wp-login.php?redirect_to=' . urlencode(get_permalink($post->ID)) . '&reauth=1');
	}


	/**
	 * Generate a string like a slug, but deleting signs and spaces,
	 * converting to lowercase and so on
	 *
	 * @static
	 * @param string $str
	 * @return string
	 */
	public static function slug ($str)
	{
		$str = strtolower(trim($str));
		$str = preg_replace('/[^a-z0-9-]/', '', $str);
		$str = preg_replace('/-+/', "-", $str);
		return $str;
	}

	/**
	 * This is the key to be stored and relationed with categories
	 * It is a string consisting of 'category_' then the category name
	 * as self::slug() and '_is_secured'
	 *
	 * @static
	 * @param string $str
	 * @return string
	 */
	public static function option_string ($str)
	{
		return 'category_' . self::slug($str) . '_is_secured';
	}

	/**
	 * Determine whether current category or post is secured or not
	 *
	 * @static
	 * @return bool
	 */
	public static function is_secured ()
	{

		/**
		 * If is category, pull terms and just determine if it's secured
		 */
		if (is_category())
		{
			$term = get_category(get_query_var('cat'));
			return (bool) get_option(self::option_string($term->name));
		}

		/**
		 * If is single we need to loop through categories to determine
		 * if any is secured
		 */
		else if (is_single())
		{
			global $post;
			$categories = get_the_terms($post->id, 'category');

			if (is_array($categories) and (bool) count($categories))
				foreach ($categories as $cat)
				{
					if ((bool) get_option(self::option_string($cat->name)) === true)
						return true;
				}
			
		}

		/**
		 * If it is other scenario, just let it go
		 */
		return false;
	}

}

// End of file ./wp-secure-categories.php