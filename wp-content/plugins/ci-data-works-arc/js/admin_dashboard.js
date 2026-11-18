var btnDown = null;
var btnUp = null;

function adjustOptionsWidth($options) {
  let maxWidth = 0;
  $options.find("li").each(function () {
    const w = $(this).outerWidth();
    if (w > maxWidth) maxWidth = w;
  });

  if ($options.find("li").length > 13) $options.attr("showScroll", 1);
  else $options.attr("showScroll", 0);
  $options.css("width", maxWidth + 40 + "px");
}

function uiScrollBox(empList) {
  var $options = $(empList);
  var empFathe = $($options).parent();
  var op = $(empFathe).find(".options");
  var showScroll = op.attr("showScroll");
  if (showScroll == 0) {
    if (btnDown != null) {
      $(btnDown).hide();
      $(btnUp).hide();
    }
    return false;
  }

  var btnUpEmp = $('<div class="select-list-scroll-icon up"></div>');
  var btnDownEmp = $('<div class="select-list-scroll-icon down"></div>');

  if (btnDown == null) {
    $("body").append(btnUpEmp);
    $("body").append(btnDownEmp);
    btnDown = btnUpEmp;
    btnUp = btnDownEmp;
  } else {
    $(btnDown).show();
    $(btnUp).show();
  }

  var pos = $options.offset();
  var height = $options.outerHeight();
  var width = $options.outerWidth();

  $(".select-list-scroll-icon").css({
    position: "fixed",
    left: pos.left + width - 8,
    cursor: "pointer",
    "z-index": 9999,
    "font-size": "18px",
    color: "#333",
  });

  btnUp.css("top", pos.top + 40);
  btnDown.css("top", pos.top + height - btnDown.outerHeight() + 369);
}
function renderTags($select, $selected) {
  const activeValues = [];
  const $wrapper = $select.next(".custom-select");
  const $options = $wrapper.find(".options");
  const placeholder = $select.attr("data-placeholder") || $select.find('option[value=""]').text() || "Select...";

  $options.find("li.active").each(function () {
    activeValues.push($(this).data("value"));
  });

  if (activeValues.length === 0) {
    $selected.html(`<span class="placeholder">${placeholder}</span>`);
    return;
  }

  let tagsHtml = '<div class="tags-container flex flex-wrap gap-1 flex-1">';

  activeValues.forEach((value) => {
    // ✅ LẤY TEXT TỪ OPTION – không đổi UI
    const label = $select.find(`option[value="${value}"]`).text();

    tagsHtml += `
      <span
        class="tag inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs
               transition-colors focus:outline-none focus:ring-2 focus:ring-ring
               focus:ring-offset-2 border-transparent bg-secondary
               text-secondary-foreground hover:bg-secondary/80 mr-1 mb-1"
        data-value="${value}"
        title="${value}"
      >
        ${label}
        <span
          class="tag-remove ml-1 ring-offset-background rounded-full outline-none
                 focus:ring-2 focus:ring-ring focus:ring-offset-2 cursor-pointer"
          data-value="${value}"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
               viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
               class="lucide lucide-x h-3 w-3 text-muted-foreground
                      hover:text-foreground">
            <path d="M18 6 6 18"></path>
            <path d="m6 6 12 12"></path>
          </svg>
        </span>
      </span>
    `;
  });

  tagsHtml += '</div>';
  $selected.html(tagsHtml);

  // remove tag
  $selected.find(".tag-remove").off('click').on("click", function (e) {
    e.stopPropagation();
    const valueToRemove = $(this).data("value");
   
    $options.find(`li[data-value="${valueToRemove}"]`).removeClass("active");

    const newValues = [];
    $options.find("li.active").each(function () {
      newValues.push($(this).data("value"));
    });
    $(this).closest(".tag").remove();
    if (newValues.length === 0) {
      $selected.html(`<span class="placeholder">${placeholder}</span>`);
    }
    $select.val(newValues).trigger("change");
  });
}

function renderSearch($options) {
  let $searchBox = $(`<div data-slot="command-input-wrapper" class="flex h-9 search items-center gap-2 border-b px-3">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search size-4 shrink-0 opacity-50"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
        </div>`);
  let $searchInput = $('<input data-slot="command-input" class="placeholder:text-muted-foreground  search-input-sel-customer flex h-10 w-full rounded-md bg-transparent py-3 text-sm outline-hidden disabled:cursor-not-allowed disabled:opacity-50 px-2" placeholder="Search..." cmdk-input="" autocomplete="off" autocorrect="off" spellcheck="false" aria-autocomplete="list" role="combobox" aria-expanded="true" aria-controls="radix-:r3p:" aria-labelledby="radix-:r3q:" id="radix-:r3r:" type="text" value="" aria-activedescendant="radix-:r45:">');

  $searchInput.on('click', function (e) {
    e.stopPropagation();
  });

  $searchInput.on('input', function (e) {
    e.stopPropagation();
    const searchTerm = $(this).val().toLowerCase();

    $options.find("li").each(function () {
      const $li = $(this);
      const text = $li.text().toLowerCase();

      if (text.includes(searchTerm)) {
        $li.show();
      } else {
        $li.hide();
      }
    });
  });

  $searchBox.append($searchInput);
  $options.prepend($searchBox);
}
function applyOptionAttrs($opt, $li) {
  if ($opt.is(":disabled") || $opt.attr("disabled") !== undefined) {
    $li.addClass("disabled").attr("aria-disabled", "true");
  }

  if ($opt.attr("title")) {
    $li.attr("title", $opt.attr("title"));
  }

  const extraClass = $opt.attr("class");
  if (extraClass) {
    $li.addClass(extraClass);
  }

  const inlineStyle = $opt.attr("style");
  if (inlineStyle) {
    $li.attr("style", inlineStyle);
  }

  if ($opt[0] && $opt[0].attributes) {
    Array.from($opt[0].attributes).forEach((attr) => {
      if (attr.name.startsWith("data-") && attr.name !== "data-value") {
        $li.attr(attr.name, attr.value);
      }
    });
  }
}

window.initCustomSelect = function (selector, class_emp = "") {
  $(selector).each(function () {
    let $select = $(this);
    var $id_emp = $(this).attr("id") || "custom_select_" + Math.random().toString(36).substr(2, 9);
    const isMulti = $select.attr("data-multi") === "1" || $select.attr("multiple") !== undefined;
    const isSearch = $select.attr("data-search") === "1";

    if ($select.data("customized")) {
      let $wrapper = $select.next(".custom-select");
      let $selected = $wrapper.find(".selected");
      let $options = $wrapper.find(".options");

      $options.find("li").removeClass("active");
      if (isMulti) {
        const values = $select.val() || [];
        values.forEach((v) => {
          $options.find(`li[data-value="${v}"]`).addClass("active");
        });
      } else {
        $options.find(`li[data-value="${$select.val()}"]`).addClass("active");
        $selected.text($select.find("option:selected").text());
      }

      return;
    }

    $select.data("customized", true);
    $select.hide();

    let $wrapper = $(
      '<div id="emp_' +
      $id_emp +
      '" class="custom-select ' +
      class_emp +
      (isMulti ? ' multi-select' : '') +
      '"></div>'
    );
    let $selected = $('<div class="selected"></div>');
    const placeholder = $select.attr("data-placeholder") || $select.find('option[value=""]').text() || "Select...";

    if (isMulti) {
      $selected.html(`<span class="placeholder">${placeholder}</span>`);
    } else {
      const selectedText = $select.find("option:selected").text();
      $selected.text(selectedText || placeholder);
    }

    let $options = $('<ul class="options"></ul>');

    if (isSearch) {
      renderSearch($options)
    }

    $select.find("option").each(function () {
      let $opt = $(this);
      const placeholder = $select.attr("data-placeholder") || $select.find('option[value=""]').text() || "Select...";
      if ($opt.val() === "" && $opt.text() === placeholder) return;

      let $li = $("<li></li>").attr("data-value", $opt.val());
      if (isMulti) {
        $li.html($opt.html());
      } else {
        $li.text($opt.text());
      }
      applyOptionAttrs($opt, $li);

      if ($opt.is(":selected")) $li.addClass("active");

      $li.off("click").on("click", function (e) {
        e.stopPropagation();

        if (isMulti) {
          $li.toggleClass("active");

          const activeValues = [];
          $options.find("li.active").each(function () {
            activeValues.push($(this).data("value"));
          });

          $select.val(activeValues);
          $select.trigger("change");

          renderTags($select, $selected);
        } else {
          $selected.text($opt.text());
          $options.find("li").removeClass("active");
          $li.addClass("active");
          $select.val($opt.val());
          $select.trigger("change");
          $options.hide();
        }
      });

      $options.append($li);
    });

    $selected.on("click", function () {
      $(".custom-select .options").not($options).hide();
      $options.toggle();

      if (isMulti && $options.is(':visible')) {
        setTimeout(() => {
          $options.find('.search-input-sel-customer').focus();
        }, 100);
      }
    });

    $wrapper.append($selected).append($options);
    $select.after($wrapper);
    adjustOptionsWidth($options);
    if (isMulti) {
      renderTags($select, $selected);
    }
  });

  $(document).on("click", function (e) {
    if (!$(e.target).closest(".custom-select").length) {
      $(".custom-select .options").hide();
      if (typeof btnDown !== 'undefined' && btnDown != null) {
        $(btnDown).hide();
        $(btnUp).hide();
      }
    }
  });
};

window.refreshCustomSelect = function (id) {
  let $select = $("#" + id);
  let $wrapper = $select.next(".custom-select");
  let $selected = $wrapper.find(".selected");
  let $options = $wrapper.find(".options");
  const isMulti = $select.attr("data-multi") === "1" || $select.attr("multiple") !== undefined;
  const isSearch = $select.attr("data-search") === "1";

  const placeholder = $select.attr("data-placeholder") || $select.find('option[value=""]').text() || "Select...";

  // clear options
  $options.empty();

  if (isSearch) {
    renderSearch($options);
  }

  const selectedValues = isMulti
    ? ($select.val() || [])
    : [$select.val()];

  // rebuild options
  $select.find("option").each(function () {
    let $opt = $(this);
    const placeholder = $select.attr("data-placeholder") || $select.find('option[value=""]').text() || "Select...";
    if ($opt.val() === "" && ($opt.text() === placeholder || $opt.text().toLowerCase() === placeholder.toLowerCase())) return;

    let value = $opt.val();
    let $li = $("<li></li>")
      .attr("data-value", value)
      .html($opt.html());

    applyOptionAttrs($opt, $li);
    if (selectedValues.includes(value)) {
      $li.addClass("active");
    }

    $li.off("click").on("click", function (e) {
      e.stopPropagation();

      if (isMulti) {
        $li.toggleClass("active");

        const newValues = [];
        $options.find("li.active").each(function () {
          newValues.push($(this).data("value"));
        });

        $select.val(newValues).trigger("change");
        renderTags($select, $selected);
      } else {
        $options.find("li").removeClass("active");
        $li.addClass("active");
        $select.val(value).trigger("change");
        $selected.text($opt.text());
        $options.hide();
      }
    });

    $options.append($li);
  });

  // render selected UI
  if (isMulti) {
    renderTags($select, $selected);
  } else {
    const selectedText =
      $select.find("option:selected").text() || placeholder;
    $selected.text(selectedText);
  }
};


window.update_ui_select = function () {
  initCustomSelect("select");
};

window.showLoading = function (divId, overall = false) {
  const parent = document.getElementById(divId);
  if (!parent) return;

  if (getComputedStyle(parent).position === "static") {
    parent.style.position = "relative";
  }

  if (parent.querySelector(".custom-loading-overlay") && !overall) return;

  const overlay = document.createElement("div");
  overlay.className = "custom-loading-overlay";
  overlay.style.position = "absolute";
  overlay.style.inset = "0"; // top:0; right:0; bottom:0; left:0
  overlay.style.background = "rgba(255, 255, 255, 0.86)";
  overlay.style.display = "flex";
  overlay.style.alignItems = "center";
  overlay.style.justifyContent = "center";
  overlay.style.zIndex = "100";
  overlay.style.borderRadius = "20px";

  // spinner
  overlay.innerHTML = `
							<svg  xmlns="http://www.w3.org/2000/svg" width="64" height="18" viewBox="0 0 64 18" aria-hidden="true" role="img">
								<circle cx="9" cy="9" r="6" fill="#6B7280">
									<animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite" begin="0s" />
								</circle>
								<circle cx="32" cy="9" r="6" fill="#6B7280">
									<animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite" begin="0.15s" />
								</circle>
								<circle cx="55" cy="9" r="6" fill="#6B7280">
									<animate attributeName="opacity" values="0.2;1;0.2" dur="1s" repeatCount="indefinite" begin="0.3s" />
								</circle>
							</svg>
		`;

  const style = document.createElement("style");
  style.textContent = `
			@keyframes spin {
			0% { transform: rotate(0deg); }
			100% { transform: rotate(360deg); }
			}
		`;
  document.head.appendChild(style);

  parent.prepend(overlay);
};

window.hideLoading = function (divId) {
  const parent = document.getElementById(divId);
  if (!parent) return;
  const overlay = parent.querySelector(".custom-loading-overlay");
  if (overlay) overlay.remove();
};

if (typeof showToast !== "function") {
  function showToast(type, title, message, duration = 3000, position = "top_right") {
    const containerId = `toast-container-${position}`;

    let container = document.getElementById(containerId);
    if (!container) {
      container = document.createElement("div");
      container.id = containerId;
      container.className = `toast-container ${position}`;
      document.body.appendChild(container);
    }

    const toast = document.createElement("div");
    toast.className = `toast ${type}`;

    let icon = "";
    if (type === "info") icon = "ℹ️";
    if (type === "success") icon = "✔️";
    if (type === "error") icon = "⚠️";
    if (type === "warning") icon = "⚠️";

    toast.innerHTML = `
    <div class="icon">${icon}</div>
    <div class="content">
      <strong>${title}</strong><br>
      <span>${message}</span>
    </div>
  `;

    container.appendChild(toast);

    setTimeout(() => toast.classList.add("show"), 100);

    if (duration > 0) {
      setTimeout(() => {
        toast.classList.remove("show");
        setTimeout(() => toast.remove(), 300);
      }, duration);
    }
    return toast;
  }
}

jQuery(function ($) {
  $.fn.sort_table = function () {
    if ($(this).data('sortable-initialized')) {
      console.log('initialized sortable table skipped');
      return;
    }
    $(this).data('sortable-initialized', true);
    console.log('initialize sortable table');
    let bsorticon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up-down h-3 w-3 opacity-30" aria-hidden="true"><path d="m21 16-4 4-4-4"></path><path d="M17 20V4"></path><path d="m3 8 4-4 4 4"></path><path d="M7 4v16"></path></svg>';
    let sortdownicon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-down h-3 w-3" aria-hidden="true"><path d="M12 5v14"></path><path d="m19 12-7 7-7-7"></path></svg>';
    let sortupicon = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-up h-3 w-3" aria-hidden="true"><path d="m5 12 7-7 7 7"></path><path d="M12 19V5"></path></svg>';
    $(this).find('tr:not(.no_sort) th').each(function (column) {
      if ($(this).hasClass('no_sort')) {
        console.log('skip sort for column', column);
        return;
      }
      console.log('init sort for column', column);
      var $th = $(this);
      var asc = true;
      $th.css('cursor', 'pointer');
      $th.append(' <span class="sort-icon inline-flex items-center">' + bsorticon + '</span>');
      $th.click(function () {
        var $table = $th.closest('table');

        $('tbody', $table).each(function () {
          let $tbody = $(this);
          let $rows = $tbody.find('tr').get();
          $rows.sort(function (a, b) {
            var A = getVal(a);
            var B = getVal(b);
            if ($.isNumeric(A) && $.isNumeric(B)) {
              console.log('num:', A, B);
              return asc ? A - B : B - A;
            } else {
              console.log('text:', A, B);
              return asc ? A.localeCompare(B) : B.localeCompare(A);
            }
          });

          $.each($rows, function (index, row) {
            $tbody.append(row);
          });
        })

        asc = !asc;
        $table.find('th span.sort-icon').html(bsorticon);
        $('.sort-icon', $th).html((!asc ? sortupicon : sortdownicon) + '');

      });

      function getVal(row) {
        let $td = $(row).children('td').eq(column);

        let sortVal = $td.data('sortval');
        let sortVal2 = $td.data('sortval2');

        if (typeof sortVal === 'number') {

          let main = sortVal;
          let sub = (typeof sortVal2 === 'number') ? sortVal2 : 0;

          const MULTIPLIER = 100000;
          return main * MULTIPLIER + sub;
        }

        let val = (sortVal !== undefined) ? sortVal : $td.text();
        return (typeof val === 'string') ? val.trim() : val;
      }
    });
  }
})

jQuery(document).ready(function ($) {

  $('table.sortabletable').each(function () {
    if (!$(this).hasClass('no_sortable')) {
      $(this).sort_table();
    }
  })
  $(document).on('mouseenter', '.tooltip-trigger', function (e) {
    let $trigger = $(this);
    let tooltipText = $trigger.data('tooltip') || '';
    if (tooltipText == '') return;
    let tooltipCallback = $trigger.data('tooltip-callback') || null;
    if (tooltipCallback && typeof window[tooltipCallback] === 'function') {
      tooltipText = window[tooltipCallback](tooltipText);
    }
    if (tooltipText == '') return;

    let $tooltip = $(`<div class="custom-tooltip text-primary-foreground animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 w-fit origin-(--radix-tooltip-content-transform-origin) rounded-md px-3 py-1.5 text-xs text-balance max-w-xs bg-white border border-gray-200 [&>svg]:fill-white">${tooltipText}</div>`);
    $('body').append($tooltip);
    let tooltipWidth = $tooltip.outerWidth();
    let tooltipHeight = $tooltip.outerHeight();
    let pleft = $trigger.offset().left + $trigger.outerWidth() / 2 - tooltipWidth / 2;
    if (pleft < 160) pleft = 160;
    $tooltip.css({
      top: $trigger.offset().top - tooltipHeight - 5 + 'px',
      left: pleft + 'px',
      position: 'absolute',
      'z-index': 1000,
    });
    $tooltip.on('mouseenter', function () {
      clearTimeout($trigger.data('timeout'));
    });
    $tooltip.on('mouseleave', function () {
      $tooltip.remove();
    });
    $trigger.data('tooltipobj', $tooltip);

  }).on('mouseleave', '.tooltip-trigger', function () {
    let $trigger = $(this);
    $trigger.data('timeout', setTimeout(function () {
      $trigger.data('tooltipobj')?.remove();
    }, 200));
  });

  $(window).scroll(function () {
    let scrolltop = $(window).scrollTop();
    if (scrolltop % 5 == 0) {
      // console.log('scrollTop:', scrolltop, 'menuheight:', $('#adminmenu').height());
      if (scrolltop + $(window).height() > $('#adminmenu').height()) {
        $(document).trigger('wp-pin-menu');
      }
    }

  });

});