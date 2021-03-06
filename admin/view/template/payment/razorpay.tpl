<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
  <div class="page-header">
    <div class="container-fluid">
      <div class="pull-right">
        <button type="submit" form="form-razorpay" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
        <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a></div>
      <h1><?php echo $heading_title; ?></h1>
      <ul class="breadcrumb">
        <?php foreach ($breadcrumbs as $breadcrumb) { ?>
        <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
        <?php } ?>
      </ul>
    </div>
  </div>
  <div class="container-fluid">
    <?php if ($error_warning) { ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
      <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php } ?>
    <div class="panel panel-default">
      <div class="panel-heading">
        <h3 class="panel-title"><i class="fa fa-pencil"></i> <?php echo $text_edit; ?></h3>
      </div>
      <div class="panel-body">
        <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-razorpay" class="form-horizontal">
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-key-id"><span data-toggle="tooltip" title="<?php echo $help_key_id; ?>"><?php echo $entry_key_id; ?></span></label>
            <div class="col-sm-10">
              <input type="text" name="razorpay_key_id" value="<?php echo $razorpay_key_id; ?>" placeholder="<?php echo $entry_key_id; ?>" id="input-key-id" class="form-control" />
              <?php if ($error_key_id) { ?>
              <div class="text-danger"><?php echo $error_key_id; ?></div>
              <?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-key-secret"><?php echo $entry_key_secret; ?></label>
            <div class="col-sm-10">
              <input type="text" name="razorpay_key_secret" value="<?php echo $razorpay_key_secret; ?>" placeholder="<?php echo $entry_key_secret; ?>" id="input-key-secret" class="form-control" />
              <?php if ($error_key_secret) { ?>
              <div class="text-danger"><?php echo $error_key_secret; ?></div>
              <?php } ?>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-order-status"><span data-toggle="tooltip" title="<?php echo $help_order_status; ?>"><?php echo $entry_order_status; ?></span></label>
            <div class="col-sm-10">
              <select name="razorpay_order_status_id" id="input-order-status" class="form-control">
                <?php foreach ($order_statuses as $order_status) { ?>
                  <?php if (($razorpay_order_status_id and $order_status['order_status_id'] == $razorpay_order_status_id) or $order_status['order_status_id'] == 2) { ?>
                    <option value="<?php echo $order_status['order_status_id']; ?>" selected="selected"><?php echo $order_status['name']; ?></option>
                  <?php } else { ?>
                    <option value="<?php echo $order_status['order_status_id']; ?>"><?php echo $order_status['name']; ?></option>
                  <?php } ?>
                <?php } ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-payment-action"><span data-toggle="tooltip" title="<?php echo $help_payment_action; ?>"><?php echo $entry_payment_action; ?></span></label>
            <div class="col-sm-10">
              <select name="razorpay_payment_action" id="input-payment-action" class="form-control">
                <?php if ($razorpay_payment_action === 'capture') { ?>
                <option value="capture" selected="selected">Authorize and Capture</option>
                <option value="authorize">Authorize</option>
                <?php } else { ?>
                <option value="capture">Authorize and Capture</option>
                <option value="authorize" selected="selected">Authorize</option>
                <?php } ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-status"><?php echo $entry_status; ?></label>
            <div class="col-sm-10">
              <select name="razorpay_status" id="input-status" class="form-control">
                <?php if ($razorpay_status) { ?>
                <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
                <option value="0"><?php echo $text_disabled; ?></option>
                <?php } else { ?>
                <option value="1"><?php echo $text_enabled; ?></option>
                <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
                <?php } ?>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-sort-order"><?php echo $entry_sort_order; ?></label>
            <div class="col-sm-10">
              <input type="text" name="razorpay_sort_order" value="<?php if($razorpay_sort_order){echo $razorpay_sort_order;} else echo 0; ?>" placeholder="<?php echo $entry_sort_order; ?>" id="input-sort-order" class="form-control" />
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-enable-webhook"><span data-toggle="tooltip" title="<?php echo $help_enable_webhook; ?>"><?php echo $entry_enable_webhook; ?></span></label>
            <div class="col-sm-10">
              <?php if ($razorpay_enable_webhook) { $check = "checked"; }else{ $check = '';} ?>
                <input type="checkbox" style="margin-left: 0px;" name="razorpay_enable_webhook" value="1" id="input-enable-webhook" class="form-control" <?php echo $check; ?>/><br>
                <span>Enable Razorpay Webhook <a href="https://dashboard.razorpay.com/#/app/webhooks">here</a> with the URL listed below.<br/><br><?php echo $webhookUrl; ?>
                </span>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-2 control-label" for="input-webhook-secret"><?php echo $entry_webhook_secret; ?></label>
            <div class="col-sm-10">
              <input type="text" name="razorpay_webhook_secret" value="<?php echo $razorpay_webhook_secret; ?>" placeholder="<?php echo $entry_webhook_secret; ?>" id="input-webhook-secret" class="form-control" />
              <span>Webhook secret is used for webhook signature verification. This has to match the one added <a href="https://dashboard.razorpay.com/#/app/webhooks">here</a>
                </span>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php echo $footer; ?>