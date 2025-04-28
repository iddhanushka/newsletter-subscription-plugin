jQuery(document).ready(function ($) {
  $("#newsletter-form").submit(function (e) {
    e.preventDefault()

    var email = $("#newsletter-email").val()

    if (!email || !validateEmail(email)) {
      alert("Please enter a valid email address.")
      return
    }

    var data = {
      action: "ns_subscribe",
      email: email
    }

    $.post(ns_ajax_obj.ajax_url, data, function (response) {
      if (response.success) {
        alert("Subscription successful!")
      } else {
        alert(response.data)
      }
    })
  })

  function validateEmail(email) {
    var regex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/
    return regex.test(email)
  }
})
