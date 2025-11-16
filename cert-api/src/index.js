// src/index.js
import express from 'express';
import dotenv from 'dotenv';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import certificatesRouter from './routes/certificates.js';


const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);


dotenv.config({ path: path.join(__dirname, '..', '.env') });

const app = express();
const port = process.env.PORT || 4000;

app.use(express.json());


app.use('/certificates', certificatesRouter);

app.listen(port, () => {
    console.log(`Cert API rodando em http://localhost:${port}`);
    console.log("API_KEY carregada:", process.env.API_KEY);
});
