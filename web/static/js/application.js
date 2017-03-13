$(document).ready(function() {
  $('.xt-hide').on('click', function() {
    $(this).hide();
    $(this).siblings('.xt-show').show();
    $(this).parents('.panel-heading').siblings('.panel-body').hide();
  });
  $('.xt-show').on('click', function() {
    $(this).hide();
    $(this).siblings('.xt-hide').show();
    $(this).parents('.panel-heading').siblings('.panel-body').show();
  });
});
