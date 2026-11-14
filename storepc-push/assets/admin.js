/* StorePC Push – admin */
jQuery(function ($) {
  // בחירת תמונה מספריית המדיה
  var frame;
  $('#spp-pick-image').on('click', function (e) {
    e.preventDefault();
    if (!frame) {
      frame = wp.media({ title: 'בחירת תמונה להתראה', button: { text: 'בחירה' }, library: { type: 'image' }, multiple: false });
      frame.on('select', function () {
        var a = frame.state().get('selection').first().toJSON();
        var url = (a.sizes && a.sizes.large ? a.sizes.large.url : a.url);
        $('#spp-image').val(url).trigger('input');
      });
    }
    frame.open();
  });

  // תצוגה מקדימה חיה
  function preview() {
    var t = $('#spp-title').val(), b = $('#spp-body').val(), img = $('#spp-image').val();
    $('#spp-prev-title').text(t || 'כותרת ההתראה');
    $('#spp-prev-body').text(b || 'תוכן ההתראה יופיע כאן');
    if (img) { $('#spp-prev-img').attr('src', img).show(); } else { $('#spp-prev-img').hide(); }
  }
  $('#spp-title, #spp-body, #spp-image').on('input change', preview);
  preview();

  // בחירת תאריך מסמנת "מתוזמן"
  $('#spp-send-at').on('change focus', function () {
    $('input[name="when"][value="later"]').prop('checked', true);
  });

  // אישור לפני שליחה לכולם
  $('form .button-primary[name="mode"]').on('click', function (e) {
    var later = $('input[name="when"]:checked').val() === 'later';
    if (!later && !window.confirm('לשלוח את ההתראה עכשיו לכל המנויים?')) { e.preventDefault(); }
  });
});
