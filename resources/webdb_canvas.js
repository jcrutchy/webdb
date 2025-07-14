/////////////////////////////////////////////////////////////////////////////////////////////////////

// application code requires:
//var container=document.getElementById("...");
//var canvas=document.getElementById("...");
//function render() { ... }

var context=canvas.getContext("2d");

var lastX=canvas.width/2;
var lastY=canvas.height/2;
var dragStart=false;
var dragged=false;
var scaleFactor=1.01;

var savedTransforms=[];
var svg=document.createElementNS("http://www.w3.org/2000/svg","svg");
var xform=svg.createSVGMatrix();

/////////////////////////////////////////////////////////////////////////////////////////////////////

function webdb_canvas_resize(event)
{
  dragStart=false;
  dragged=false;
  context.canvas.width=container.clientWidth;
  context.canvas.height=container.clientHeight;
  context.resetTransform();
  context.reset();
  savedTransforms=[];
  svg=document.createElementNS("http://www.w3.org/2000/svg","svg");
  xform=svg.createSVGMatrix();
  lastX=canvas.width/2;
  lastY=canvas.height/2;
  render();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function webdb_canvas_mousemove(event)
{
  lastX=event.offsetX || (event.pageX-canvas.offsetLeft);
  lastY=event.offsetY || (event.pageY-canvas.offsetTop);
  dragged=true;
  if (dragStart)
  {
    var p=webdb_ctx_transformedPoint(lastX,lastY);
    webdb_ctx_translate(p.x-dragStart.x,p.y-dragStart.y);
    render();
  }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function webdb_canvas_mousedown(event)
{
  if (event.button!=0)
  {
    return;
  }
  document.body.style.userSelect="none";
  lastX=event.offsetX || (event.pageX-canvas.offsetLeft);
  lastY=event.offsetY || (event.pageY-canvas.offsetTop);
  dragStart=webdb_ctx_transformedPoint(lastX,lastY);
  dragged=false;
  render();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function webdb_canvas_mouseup(event)
{
  document.body.style.userSelect="auto";
  dragStart=null;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function webdb_canvas_wheel(event)
{
  delta=event.wheelDelta/40;
  if (delta!=0)
  {
    var factor=Math.pow(scaleFactor,delta);
    var p=webdb_ctx_transformedPoint(lastX,lastY);
    webdb_ctx_translate(p.x,p.y);
    webdb_ctx_scale(factor,factor);
    webdb_ctx_translate(-p.x,-p.y);
    render();
  }
  return event.preventDefault() && false;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function webdb_render_clear()
{
  var p1=webdb_ctx_transformedPoint(0,0);
  var p2=webdb_ctx_transformedPoint(canvas.width,canvas.height);
  context.clearRect(p1.x,p1.y,p2.x-p1.x,p2.y-p1.y);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function webdb_ctx_save()
{
  savedTransforms.push(xform.translate(0,0));
  return context.save();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function webdb_ctx_restore()
{
  xform=savedTransforms.pop();
  return context.restore();
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function webdb_ctx_scale(s)
{
  xform=xform.scaleNonUniform(s,s);
  return context.scale(s,s);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function webdb_ctx_translate(dx,dy)
{
  xform=xform.translate(dx,dy);
  return context.translate(dx,dy);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function webdb_ctx_transform(a,b,c,d,e,f)
{
  var m2=svg.createSVGMatrix();
  m2.a=a;
  m2.b=b;
  m2.c=c;
  m2.d=d;
  m2.e=e;
  m2.f=f;
  xform=xform.multiply(m2);
  return context.transform(a,b,c,d,e,f);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function webdb_ctx_setTransform(a,b,c,d,e,f)
{
  xform.a=a;
  xform.b=b;
  xform.c=c;
  xform.d=d;
  xform.e=e;
  xform.f=f;
  return context.setTransform(a,b,c,d,e,f);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////

function webdb_ctx_transformedPoint(x,y)
{
  var p=svg.createSVGPoint();
  p.x=x;
  p.y=y;
  return p.matrixTransform(xform.inverse());
}

/////////////////////////////////////////////////////////////////////////////////////////////////////
