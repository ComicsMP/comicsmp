<?php
// comicsmp/mobile/manual_comic.php
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Manual Comic Add</title>
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
  >
  <style>
    body {
      background: #f4f4f4;
      padding: 1rem;
      font-family: 'Roboto', sans-serif;
    }
    h4 { font-weight: 600; margin-bottom: 1rem; }

    .card {
      border-radius: 12px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .form-label { font-weight: bold; }
    .btn { border-radius: 8px; }

    /* suggestions */
    #suggestions .suggestion-item {
      padding: 0.5rem 0.75rem;
      border: 1px solid #ccc;
      background: #fff;
      cursor: pointer;
    }
    #suggestions .suggestion-item:hover {
      background: #e9ecef;
    }

    /* controls */
    #controls {
      display: none;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 0.75rem;
      flex-wrap: wrap;
      gap: 0.5rem 1rem;
    }
    #tabsContainer .nav-tabs {
      flex-wrap: wrap;
      margin-bottom: 0.5rem;
    }

    /* responsive grid */
    #coversContainer {
      display: grid;
      gap: 0.5rem;
      grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    }
    .gallery-item {
      background: #fff;
      border-radius: 8px;
      padding: 0.5rem;
      text-align: center;
      box-shadow: 0 1px 4px rgba(0,0,0,0.1);
    }
    .gallery-item img {
      width: 100%;
      height: auto;
      border-radius: 4px;
    }
    .gallery-item .btn {
      font-size: 0.8rem;
      padding: 0.35rem;
      margin-top: 0.3rem;
      width: 100%;
    }
  </style>
</head>
<body>

  <div class="container-fluid">
    <h4>Add Comic Manually</h4>

    <!-- SEARCH FORM -->
    <div class="card mb-3 p-3">
      <form id="manualAddForm" autocomplete="off">
        <div class="mb-3">
          <label class="form-label" for="comicTitle">Comic Title</label>
          <input type="text" id="comicTitle" class="form-control" placeholder="Start typing…">
          <div id="suggestions" class="mt-1"></div>
        </div>

        <div class="row g-2 mb-3">
  <div class="col-6">
    <label class="form-label" for="countrySelect">Country</label>
    <select id="countrySelect" class="form-select">
      <option value="USA" selected>USA</option>
      <option value="Canada">Canada</option>
      <option value="UK">UK</option>
    </select>
  </div>
  <div class="col-6">
    <label class="form-label" for="yearSelect">Year</label>
    <select id="yearSelect" class="form-select">
      <option selected disabled>Select Year</option>
    </select>
  </div>
</div>


        <button type="button" id="searchCoversBtn" class="btn btn-secondary w-100">
          Search Covers
        </button>
      </form>
    </div>

    <!-- SERIES HEADER (once) -->
    <div id="seriesHeader" class="mb-2"></div>

    <!-- CONTROLS -->
    <div id="controls" class="d-flex">
      <div id="tabsContainer"></div>
      <div class="d-flex align-items-center gap-2">
        <select id="issueFilterSelect" class="form-select form-select-sm" style="width: auto;">
          <option value="">All Issues</option>
        </select>
        <div class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" id="includeVariantsCheckbox">

          <label class="form-check-label small" for="includeVariantsCheckbox">Variants</label>
        </div>
      </div>
    </div>

    <!-- COVERS GRID -->
    <div id="coversContainer"></div>
  </div>

  <!-- jQuery + custom JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
  $(function(){
    let currentTab = 'All',
        includeVariants = localStorage.getItem('includeVariants') === 'true',
        currentIssueFilter = '',
        availableTabs = null;
        $('#includeVariantsCheckbox').prop('checked', includeVariants);

    const limit = 50;
    let offset = 0,
        loading = false,
        moreToLoad = true;

    // 1) TITLE SUGGESTIONS
    $('#comicTitle').on('input', function(){
      const q = this.value;
      if (q.length >= 2) {
        $.get('/comicsmp/suggest.php',{q},html=>{
          $('#suggestions').html(html).slideDown();
        });
      } else {
        $('#suggestions').slideUp().empty();
      }
    });
    $(document).on('click','.suggestion-item',function(){
      $('#comicTitle').val(this.textContent);
      $('#suggestions').slideUp().empty();
      const country = $('#countrySelect').val();
$.get('/comicsmp/getYears.php',{
  comic_title: this.textContent,
  country: country
}, options => {
  $('#yearSelect').html(options);
});
    });
// 1A) COUNTRY CHANGED AFTER TITLE — reload years
$('#countrySelect').on('change', function(){
  const title = $('#comicTitle').val();
  if (title.length >= 2) {
    $.get('/comicsmp/getYears.php', {
      comic_title: title,
      country: this.value
    }, options => {
      $('#yearSelect').html(options);
    });
  }
});

    // 2) SEARCH COVERS
    $('#searchCoversBtn').click(function(){
      const title = $('#comicTitle').val(),
            yr    = $('#yearSelect').val(),
            ctry  = $('#countrySelect').val();
      if (!title||!yr) return alert('Please select both title and year.');
      includeVariants = $('#includeVariantsCheckbox').prop('checked');
      currentTab = 'All'; currentIssueFilter=''; availableTabs=null;
      offset=0; moreToLoad=true;
      $('#issueFilterSelect').html('<option value="">All Issues</option>');
      $('#seriesHeader,#tabsContainer,#coversContainer').empty();
      $('#controls').hide();

      $.get('/comicsmp/getIssues.php',{
          comic_title: title,
          year: yr,
          include_variants: includeVariants?1:0
        },
        options=>{
          $('#issueFilterSelect').html('<option value="">All Issues</option>'+options);
          loadCovers();
        }
      );
    });

    // 3) LOAD COVERS
    function loadCovers(){
      if(loading||!moreToLoad) return;
      loading=true; $('#controls').fadeIn();
      if(offset===0){
        $('#coversContainer').html('Loading covers…');
      } else {
        $('#coversContainer').append('<div id="loadingMore">Loading more…</div>');
      }

      $.ajax({
        url:'/comicsmp/searchResults.php',
        data:{
          comic_title:$('#comicTitle').val(),
          year:$('#yearSelect').val(),
          country:$('#countrySelect').val(),
          tab:currentTab,
          issue_number:currentIssueFilter,
          include_variants:includeVariants?1:0,
          limit, offset
        },
        dataType:'html'
      })
      .done(html=>{
        $('#loadingMore').remove();
        const $tmp = $('<div>').append(html),
              items = $tmp.find('.gallery-item');

        if(offset===0){
          $('#seriesHeader').html($tmp.find('.series-header-box'));
          $('#coversContainer').html(items);
          const tabs = new Set(['All']);
          items.each(function(){ tabs.add($(this).data('tab')); });
          availableTabs = Array.from(tabs);
          renderTabs();
        } else {
          $('#coversContainer').append(items);
        }

        const loaded = items.length;
        offset += loaded;
        if(loaded<limit) moreToLoad=false;
        loading=false;
      })
      .fail(()=>{
        $('#loadingMore').remove();
        $('#coversContainer').append('<div class="text-danger">Error loading more covers. Check console.</div>');
        loading=false;
      });
    }

    function renderTabs(){
      let nav = '<ul class="nav nav-tabs">';
      availableTabs.forEach(t=>{
        const a = t===currentTab?' active':'';
        nav += `<li class="nav-item"><a class="nav-link${a}" data-tab="${t}">${t}</a></li>`;
      });
      nav += '</ul>';
      $('#tabsContainer').html(nav);
    }

    // 4) INFINITE SCROLL
    $(window).on('scroll',function(){
      if(!moreToLoad||loading) return;
      if($(window).scrollTop()+$(window).height()+100 >= $(document).height()){
        loadCovers();
      }
    });

    // 5) TAB CLICK
    $('#tabsContainer').on('click','.nav-link',function(){
      currentTab = $(this).data('tab');
      $('#tabsContainer .nav-link').removeClass('active');
      $(this).addClass('active');
      resetAndReload();
    });

    // 6) ISSUE FILTER
    $('#issueFilterSelect').on('change',function(){
      currentIssueFilter = this.value||'';
      resetAndReload();
    });

    // 7) VARIANTS TOGGLE
    $('#includeVariantsCheckbox').on('change',function(){
      localStorage.setItem('includeVariants', this.checked);
      includeVariants = this.checked;
      const title = $('#comicTitle').val(),
        yr = $('#yearSelect').val(),
        selectedIssue = $('#issueFilterSelect').val();

  if (!title || !yr) return;

  $.get('/comicsmp/getIssues.php', {
    comic_title: title,
    year: yr,
    include_variants: includeVariants ? 1 : 0
  }, options => {
    $('#issueFilterSelect').html('<option value="">All Issues</option>' + options);

    // Re-apply selected issue if it's still in list
    if (selectedIssue) {
      $('#issueFilterSelect').val(selectedIssue);
      currentIssueFilter = selectedIssue;
    } else {
      currentIssueFilter = '';
    }

    resetAndReload();
  });
});


    function resetAndReload(){
      offset=0; moreToLoad=true;
      $('#coversContainer').empty();
      loadCovers();
    }

    // 8) WANTED
    $(document).on('click','.add-to-wanted',function(){
      const $btn = $(this), $it = $btn.closest('.gallery-item'),
            p = {
              comic_title:  $it.data('comicTitle')  || $it.data('comic-title'),
              issue_number: $it.data('issueNumber') || $it.data('issue-number'),
              years:        $it.data('year')        || $it.data('years')
            };
      if(!p.comic_title||!p.issue_number||!p.years){
        return alert('Error: missing fields on the item');
      }
      $btn.prop('disabled',true).text('Processing…');
      $.post('/comicsmp/addToWanted.php',p,res=>{
        if(res.status==='success'){
          $btn.removeClass('btn-secondary').addClass('btn-success').text('Added');
        } else {
          alert('Error: '+(res.message||'Failed'));
          $btn.prop('disabled',false).text('Wanted');
        }
      },'json').fail(()=>{ alert('Network error.'); $btn.prop('disabled',false).text('Wanted'); });
    });

    // 9) SELL → desktop modal
    let _pendingSell = null;
    $(document).on('click','.sell-button',function(){
      _pendingSell = $(this).closest('.gallery-item');
      // prepare desktop form
      $('#editSaleForm')[0].reset();
      $('#editListingId').val('');
      $('#editCondition').val('');
      $('#editGraded').val('0');
      $('#editPrice').val('');
      // inject hidden comic data
      $('#editSaleForm').find('input[name="comic_title"],input[name="issue_number"],input[name="years"],input[name="issue_url"]').remove();
      $('#editSaleForm').append(`
        <input type="hidden" name="comic_title"  value="${_pendingSell.data('comicTitle')}">
        <input type="hidden" name="issue_number" value="${_pendingSell.data('issueNumber')}">
        <input type="hidden" name="years"        value="${_pendingSell.data('years')}">
        <input type="hidden" name="issue_url"    value="${_pendingSell.data('issueUrl')}">
      `);
      new bootstrap.Modal(document.getElementById('editSaleModal')).show();
    });

    // 10) SUBMIT desktop form
    $(document).on('submit','#editSaleForm',function(e){
      e.preventDefault();
      const fd = $(this).serialize();
      $.post('/comicsmp/addListing.php',fd,function(resp){
        if(resp.status==='success'){
          _pendingSell.find('.sell-button')
            .removeClass('btn-secondary').addClass('btn-success')
            .text('Listed').prop('disabled',true);
          bootstrap.Modal.getInstance(document.getElementById('editSaleModal')).hide();
        } else {
          alert(resp.message||'Listing failed.');
        }
      },'json').fail(()=>{ alert('Server error while adding the listing.'); });
    });

  });
  </script>

  


  <!-- include shared modals -->
  <?php include __DIR__ . '/../includes/Modals.php'; ?>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
