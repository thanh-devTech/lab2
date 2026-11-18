const net = require("net");
const fs = require('fs');


const port = 9100;




const server = net.createServer((socket) => {
    console.log("Client connected");
    let strData = '';
    socket.on("data", (data) => {
        strData += data.toString();

        let idx = strData.indexOf('^XZ');
        if (idx > -1) {
            let content = strData.substring(0, idx + 3);
            strData = strData.substring(idx + 3);
            $filename = Date.now() + '.zpl';
            let filepath = __dirname + '/log/' + $filename;
            fs.writeFile(filepath, content, err => {
                if (err) {
                    console.error(err);
                }
                // file written successfully
            });
        }
    });

    socket.on("end", () => {
        console.log("Client disconnected");
    });

    socket.on("error", (error) => {
        console.log(`Socket Error: ${error.message}`);
    });
});

server.on("error", (error) => {
    console.log(`Server Error: ${error.message}`);
});

server.listen(port, () => {
    console.log(`TCP socket server is running on port: ${port}`);
});