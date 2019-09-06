<div class="wrap">

  <div id="overwrite_schedule_list">

    <h1 class="wp-heading-inline"><?php _e( 'Scheduled Posts', 'hasp' ) ?></h1>
	<hr class="wp-header-end">

    <ul class="subsubsub">
      <?php $con = 0; ?>
      <?php foreach ($quick_links as $ql) : ?>
        <?php $con++; ?>
        <li>
          <a href="<?php echo $ql['href'] ?>" <?php if($ql['current'] === 1) echo 'class="current" aria-current="page"'; ?> ><?php echo $ql['status']; ?>
            <span class="count">(<?php echo $ql['count']; ?>)</span>
          </a>
        </li>
        <?php if($con < count($quick_links)) echo "|"; ?>
      <?php endforeach; ?>
    </ul>
    <div class="alignright">
	    <input type="text" name="date" id="view_date" placeholder="<?php _e( 'Select Date', 'hasp' ); ?>" value="<?php echo $view_date; ?>">
	</div>

    <table class="wp-list-table widefat striped sch_post">
      <thead>
        <tr>
          <th scope="col" class="manage-column column-primary <?php echo ($post_order_by == "totl")? "sorted": "sortable";?> <?php echo $order; ?>">
            <a href="<?php echo $hasp_url; ?>&amp;orderby=totl&amp;order=<?php echo (($order == "desc") ? "asc": "desc"); ?>">
            	<span><?php _e( 'Title', 'hasp' ) ?></span>
				<span class="sorting-indicator"></span>
            </a>
          </th>
          <th scope="col" class="manage-column">
            <span><?php _e( 'Post Status', 'hasp' ) ?></span>
          </th>
          <th scope="col" class="manage-column <?php echo ($post_order_by == "poty")? "sorted": "sortable";?> <?php echo $order; ?>">
            <a href="<?php echo $hasp_url; ?>&amp;orderby=poty&amp;order=<?php echo (($order == "desc") ? "asc": "desc"); ?>">
            <span><?php _e( 'Post Type', 'hasp' ) ?></span>
            <span class="sorting-indicator"></span></a>
          </th>
          <th scope="col" class="manage-column <?php echo ($post_order_by == "ptdt1")? "sorted": "sortable";?> <?php echo $order; ?>">
            <a href="<?php echo $hasp_url; ?>&amp;orderby=ptdt1&amp;order=<?php echo (($order == "desc") ? "asc": "desc"); ?>">
            <span><?php _e( 'Datetime of Schedule', 'hasp' ) ?></span>
            <span class="sorting-indicator"></span></a>
          </th>
          <th scope="col" class="manage-column <?php echo ($post_order_by == "ptdt2")? "sorted": "sortable";?> <?php echo $order; ?>">
            <a href="<?php echo $hasp_url; ?>&amp;orderby=ptdt2&amp;order=<?php echo (($order == "desc") ? "asc": "desc"); ?>">
            <span><?php _e( 'Datetime of Expire', 'hasp' ) ?></span>
            <span class="sorting-indicator"></span></a>
          </th>
          <th scope="col" class="manage-column <?php echo ($post_order_by == "ptdt3")? "sorted": "sortable";?> <?php echo $order; ?>">
            <a href="<?php echo $hasp_url; ?>&amp;orderby=ptdt3&amp;order=<?php echo (($order == "desc") ? "asc": "desc"); ?>">
            <span><?php _e( 'Datetime of Overwrite', 'hasp' ) ?></span>
            <span class="sorting-indicator"></span></a>
          </th>
          <th scope="col" class="manage-column <?php echo ($post_order_by == "topo")? "sorted": "sortable";?> <?php echo $order; ?>">
            <a href="<?php echo $hasp_url; ?>&amp;orderby=topo&amp;order=<?php echo (($order == "desc") ? "asc": "desc"); ?>">
            <span><?php _e( 'Title to be Overwritten', 'hasp' ) ?></span>
            <span class="sorting-indicator"></span></a>
          </th>
          <th scope="col" class="manage-column">
            <span><?php _e( 'Post Status', 'hasp' ) ?></span>
          </th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($future_overwrite_posts as $post) : ?>
        <tr>
          <td>
	        <strong><?php echo "<a href=" . admin_url() . "post.php?post=" . $post->post_id . "&action=edit>" . esc_html($post->post_title) . "</a>"; ?></strong>
          </td>
          <td>
            <?php _e( $post->post_status , 'hasp' ); ?>
          </td>
          <td>
            <?php
            $post_type = get_post_type( $post->post_id );
            $obj = get_post_type_object( $post_type );
            echo $obj->label;
            ?>
          </td>
          <td>
            <?php if ( isset( $post->post_date_publish ) ) echo date( "Y/m/d H:i" , strtotime( $post->post_date_publish ) ) ; ?>
          </td>
          <td>
            <?php if ( isset( $post->post_date_end ) ) echo date( "Y/m/d H:i" , strtotime( $post->post_date_end ) ) ; ?>
          </td>
          <td>
            <?php if ( isset( $post->post_date_overwrite ) ) echo date( "Y/m/d H:i" , strtotime( $post->post_date_overwrite ) ) ; ?>
          </td>
          <td>
            <?php echo "<a href=" . admin_url() . "post.php?post=" . $post->post_id_overwrite . "&action=edit>" . esc_html($post->post_title_overwrite) . "</a>"; ?>
          </td>
          <td>
            <?php echo __( $post->post_status_overwrite , 'hasp' ) ; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
