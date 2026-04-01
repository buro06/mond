function showFields(type) {
    document.getElementById('f-http').style.display = type === 'http' ? '' : 'none';
    document.getElementById('f-tcp-host').style.display = type === 'tcp' ? '' : 'none';
    document.getElementById('f-tcp-port').style.display = type === 'tcp' ? '' : 'none';
    document.getElementById('f-interval').style.display = type !== 'agent' ? '' : 'none';
    document.getElementById('f-timeout').style.display = type === 'agent' ? '' : 'none';
}