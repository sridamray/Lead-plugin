jQuery(document).ready(function ($) {
  // Toggle dropdown and search form visibility
  $(".dropdown-selected").on("click", function () {
    var $dropdown = $(this).siblings(".dropdown-content");
    $(".dropdown-content").not($dropdown).hide(); // Hide other dropdowns
    $dropdown.toggle(); // Toggle current dropdown
  });

  // Handle search input
  $(".filter-search").on("keyup", function () {
    var searchTerm = $(this).val().toLowerCase();
    var $dropdownList = $(this).siblings(".dropdown-list"); // Get the corresponding dropdown list

    // Filter the list items based on search term
    $dropdownList.find("li").each(function () {
      var listItemText = $(this).text().toLowerCase();
      if (listItemText.includes(searchTerm)) {
        $(this).show();
      } else {
        $(this).hide();
      }
    });
  });

  // Handle option selection
  $(".dropdown-list li").on("click", function () {
    var selectedValue = $(this).data("value");
    var $dropdown = $(this).closest(".custom-dropdown");

    // Update the selected value and close the dropdown
    $dropdown.find(".dropdown-selected span").text($(this).text());
    $dropdown.find(".dropdown-content").hide();
  });

  // Close the dropdown if clicked outside
  $(document).on("click", function (event) {
    if (!$(event.target).closest(".custom-dropdown").length) {
      $(".dropdown-content").hide();
    }
  });

  if (!$(event.target).closest(".custom-dropdown").length) {
    $(".dropdown-content").hide();
  }

  $("#buy-now-btn").on("click", function () {
    var filteredQuery = tdlp_data.filtered_query;
    $.ajax({
      url: tdlp_data.ajax_url,
      type: "POST",
      data: {
        action: "tdlp_save_filtered_query",
        filtered_query: filteredQuery,
      },
      success: function (response) {
        if (response.success) {
          // Proceed with adding to cart and checkout
          console.log("Filtered query saved successfully");
          // Add your cart and checkout logic here
        } else {
          console.error("Failed to save filtered query");
        }
      },
    });
  });

  // end of filtered query
});
