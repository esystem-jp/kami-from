jQuery(function($){
  const $canvas = $('#pf-canvas');
  if (!$canvas.length) return;

  const templateId = $canvas.data('template-id');
  const nonce = $canvas.data('nonce');
  const ajaxUrl = $canvas.data('ajax-url');

  function toPct(px, total){
    if (!total || total<=0) return 0;
    return (px / total) * 100;
  }

  function clamp(v, min, max){ return Math.max(min, Math.min(max, v)); }

  function syncBoxPct($box){
    const cw = $canvas.width();
    const ch = $canvas.height();
    const pos = $box.position();
    const w = $box.outerWidth();
    const h = $box.outerHeight();

    const xPct = clamp(toPct(pos.left, cw), 0, 100);
    const yPct = clamp(toPct(pos.top, ch), 0, 100);
    const wPct = clamp(toPct(w, cw), 0.1, 100);
    const hPct = clamp(toPct(h, ch), 0.1, 100);

    $box.attr('data-x-pct', xPct.toFixed(4));
    $box.attr('data-y-pct', yPct.toFixed(4));
    $box.attr('data-w-pct', wPct.toFixed(4));
    $box.attr('data-h-pct', hPct.toFixed(4));
  }

  $('.pf-field-box').each(function(){
    const $box = $(this);
    $box.draggable({
      containment: $canvas,
      stop: function(){ syncBoxPct($box); }
    }).resizable({
      containment: $canvas,
      handles: 'n,e,s,w,ne,nw,se,sw',
      stop: function(){ syncBoxPct($box); }
    });

    $box.on('click', function(){
      $('.pf-field-box').removeClass('is-selected');
      $box.addClass('is-selected');
      const fid = $box.data('field-id');
      $('#pf-selected').text('選択中: Field ID ' + fid);
    });

    // initial sync
    syncBoxPct($box);
  });

  $('#pf-save-positions').on('click', function(e){
    e.preventDefault();

    const items = [];
    $('.pf-field-box').each(function(){
      const $b = $(this);
      items.push({
        id: $b.data('field-id'),
        x_pct: $b.attr('data-x-pct'),
        y_pct: $b.attr('data-y-pct'),
        w_pct: $b.attr('data-w-pct'),
        h_pct: $b.attr('data-h-pct'),
      });
    });

    $('#pf-save-status').text('保存中…');

    $.post(ajaxUrl, {
      action: 'pf_save_positions',
      template_id: templateId,
      nonce: nonce,
      items: items
    }).done(function(res){
      if (res && res.success){
        $('#pf-save-status').text('保存しました');
      } else {
        $('#pf-save-status').text('保存に失敗しました');
      }
    }).fail(function(){
      $('#pf-save-status').text('保存に失敗しました');
    });
  });

  // keep layout responsive: on resize, reposition from pct
  function applyPctLayout(){
    const cw = $canvas.width();
    const ch = $canvas.height();
    $('.pf-field-box').each(function(){
      const $b = $(this);
      const x = parseFloat($b.data('x-pct'));
      const y = parseFloat($b.data('y-pct'));
      const w = parseFloat($b.data('w-pct'));
      const h = parseFloat($b.data('h-pct'));
      if (isFinite(x) && isFinite(y) && isFinite(w) && isFinite(h)){
        $b.css({
          left: (x/100*cw) + 'px',
          top: (y/100*ch) + 'px',
          width: (w/100*cw) + 'px',
          height: (h/100*ch) + 'px',
        });
      }
    });
  }
  $(window).on('resize', applyPctLayout);
});
