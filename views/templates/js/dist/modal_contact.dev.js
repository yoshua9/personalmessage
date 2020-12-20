"use strict";

$(document).ready(function () {
  $("#contact-us-popup").fancybox({
    type: "iframe",
    autoDimensions: false,
    autoSize: false,
    width: 800,
    height: "auto",
    afterShow: function afterShow(obj) {
      $(".contact-form-box").submit(function () {
        $(this).find(".alert.alert-danger").remove();
        var formdata = new FormData($(this)[0]);
        formdata.append("submitMessage", 1);
        var that = $(this);
        $.ajax({
          type: "POST",
          data: formdata,
          url: $(this).attr("action"),
          contentType: false,
          processData: false,
          success: function success(data) {
            var error = $($.parseHTML(data)).filter(".alert.alert-danger");
            if (error.length > 0) that.prepend(error);else {
              var success = $($.parseHTML(data)).filter(".alert.alert-success");
              that.fadeOut("fast", function () {
                $(this).after(success);
              });
            }
          }
        });
        return false;
      });
    }
  });
});