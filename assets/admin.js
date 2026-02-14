jQuery(function($){
  // Media picker for template background
  $(document).on('click', '.pf-pick-media', function(e){
    e.preventDefault();
    const $btn = $(this);
    const targetId = $btn.data('target-id');
    const targetUrl = $btn.data('target-url');
    const frame = wp.media({
      title: '背景画像を選択',
      button: { text: '選択' },
      multiple: false
    });
    frame.on('select', function(){
      const att = frame.state().get('selection').first().toJSON();
      $('#' + targetId).val(att.id);
      $('#' + targetUrl).val(att.url);
      $('.pf-bg-preview').attr('src', att.url).show();
    });
    frame.open();
  });
});
