function show(){
    document.querySelector('.form').classList.toggle('show');
}
function edit(){
    document.querySelector('.edit').classList.toggle('show');
}
function addImage() {
    const fileInput = document.getElementById('file');
    fileInput.click();
}