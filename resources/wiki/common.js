/////////////////////////////////////////////////////////////////////////////////////////////////////

function wiki_page_load()
{
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function update_file_title()
{
  var exist_title=document.getElementById("wiki_file_edit_title").value;
  if (exist_title=="")
  {
    var filename=document.getElementById("wiki_file_upload").value;
    filename=filename.split("\\").pop();
    filename=filename.split("/").pop();
    filename=filename.split(".");
    filename.pop();
    filename=filename.join(".");
    document.getElementById("wiki_file_edit_title").value=filename.trim();
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function resize_iframe_content(iframe)
{
  var content=iframe.contentWindow.document.body;
  var h=content.scrollHeight;
  if (h!=0)
  {
    iframe.style.width=content.scrollWidth+"px";
    iframe.style.height=h+"px";
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

if (window.addEventListener)
{
  window.addEventListener("load",wiki_page_load);
}
else
{
  window.attachEvent("onload",wiki_page_load);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
