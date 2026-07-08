
window.onload = function(){
  getRem(375,100)
};
window.onresize = function(){
  getRem(375,100)
};
function getRem(pwidth, prem){
  let html = document.getElementsByTagName("html")[0];
  let oWidth = document.body.clientWidth || document.documentElement.clientWidth;
  html.style.fontSize = oWidth / pwidth * prem + "px";
}
