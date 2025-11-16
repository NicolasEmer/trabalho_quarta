// src/routes/certificates.js
import express from 'express';
import { v4 as uuidv4 } from 'uuid';
import path from 'node:path';
import fs from 'node:fs';
import { fileURLToPath } from 'node:url';
import { renderCertificatePDF } from '../templates/certificateTemplate.js';
import db from '../db.js';

const router = express.Router();


const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const storageDir = path.join(__dirname, '..', '..', 'storage', 'certificates');


fs.mkdirSync(storageDir, { recursive: true });


function requireApiKey(req, res, next) {
    const key = req.header('X-API-Key');
    if (!key) {
        return res.status(401).json({ message: 'API Key missing' });
    }
    if (key !== process.env.API_KEY) {
        return res.status(401).json({ message: 'Invalid API Key' });
    }
    next();
}


function toMySqlDateTime(date) {
    const d = (date instanceof Date) ? date : new Date(date);
    const pad = n => String(n).padStart(2, '0');
    return (
        d.getFullYear() + '-' +
        pad(d.getMonth() + 1) + '-' +
        pad(d.getDate()) + ' ' +
        pad(d.getHours()) + ':' +
        pad(d.getMinutes()) + ':' +
        pad(d.getSeconds())
    );
}


router.get('/files/:file', (req, res) => {
    const filePath = path.join(storageDir, req.params.file);
    return res.sendFile(filePath, err => {
        if (err) {
            return res.status(404).json({ message: 'Arquivo não encontrado.' });
        }
    });
});


router.get('/verify/:code', async (req, res) => {
    const { code } = req.params;
    const wantsJson = req.accepts(['html', 'json']) === 'json';

    try {
        const [rows] = await db.execute(
            'SELECT * FROM certificates WHERE code = ? LIMIT 1',
            [code]
        );

        if (!rows.length) {
            if (wantsJson) {
                return res.status(404).json({
                    valid: false,
                    message: 'Certificado não encontrado.',
                });
            }

            return res.status(404).send(`
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Verificação de Certificado</title>
<style>
    body { font-family: system-ui; background:#f3f4f6; margin:0; }
    .page { min-height:100vh; display:flex; align-items:center; justify-content:center; }
    .card {
        background:#fff; padding:24px 32px; border-radius:12px;
        box-shadow:0 10px 30px rgba(0,0,0,0.12); max-width:540px;
    }
    .status-badge {
        padding:4px 10px; background:#fee2e2; color:#b91c1c;
        border-radius:999px; font-size:12px; font-weight:600;
        text-transform:uppercase; margin-bottom:8px;
    }
    .code { background:#f9fafb; padding:6px 8px; border-radius:6px; margin-top:8px; }
    .footer { margin-top:16px; font-size:12px; color:#9ca3af; }
</style>
</head>
<body>
<div class="page">
<div class="card">
    <div class="status-badge">Inválido</div>
    <h1>Certificado não encontrado</h1>
    <p>O código informado não corresponde a um certificado válido.</p>
    <div class="code">${code}</div>
    <p class="footer">Se você acredita que isto é um erro, entre em contato com o organizador do evento.</p>
</div>
</div>
</body>
</html>`);
        }

        const cert = rows[0];

        if (wantsJson) {
            return res.json({ valid: true, data: cert });
        }

        const issuedBr = new Date(cert.issued_at).toLocaleString('pt-BR');

        return res.send(`
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Certificado Verificado</title>
<style>
    body { font-family: system-ui; background:#f3f4f6; margin:0; }
    .page { min-height:100vh; display:flex; align-items:center; justify-content:center; }
    .card {
        background:#fff; padding:32px; border-radius:12px;
        box-shadow:0 10px 30px rgba(0,0,0,0.12); max-width:640px;
    }
    .status-badge {
        padding:4px 10px; background:#dcfce7; color:#166534;
        border-radius:999px; font-size:12px; font-weight:600;
        text-transform:uppercase; margin-bottom:8px;
    }
    .label { font-weight:600; color:#374151; }
    .value { margin-left:6px; }
    .code {
        font-family: monospace; background:#f9fafb;
        padding:6px 8px; border-radius:6px; margin-top:8px;
    }
    a { color:#2563eb; }
</style>
</head>
<body>
<div class="page">
<div class="card">

    <div class="status-badge">Válido</div>
    <h1>Certificado verificado com sucesso</h1>

    <p><span class="label">Usuário:</span> <span class="value">${cert.user_name}</span></p>
    <p><span class="label">CPF:</span> <span class="value">${cert.user_cpf || ''}</span></p>

    <p><span class="label">Evento:</span> <span class="value">${cert.event_title}</span></p>
    <p><span class="label">Data do evento:</span> <span class="value">${cert.event_start_at}</span></p>

    <p><span class="label">Código:</span></p>
    <div class="code">${cert.code}</div>

    <p><span class="label">Emitido em:</span> ${issuedBr}</p>

    ${cert.pdf_url ? `<p><a href="${cert.pdf_url}" target="_blank">Baixar certificado</a></p>` : ""}

</div>
</div>
</body>
</html>`);
    } catch (err) {
        console.error('Erro ao verificar certificado:', err);
        return res.status(500).json({ message: 'Erro ao verificar certificado.' });
    }
});

router.post('/', requireApiKey, async (req, res) => {
    try {
        const {
            user_id,
            user_name,
            user_cpf,
            event_id,
            event_title,
            event_start_at,
        } = req.body || {};

        if (!user_id || !event_id) {
            return res.status(400).json({
                message: 'user_id e event_id são obrigatórios',
            });
        }

        const safeUserName   = user_name  || 'Participante';
        const safeUserCpf    = user_cpf   || '';
        const safeEventTitle = event_title || 'Evento';
        const safeEventStart = event_start_at || new Date().toISOString();


        const [existing] = await db.execute(
            'SELECT * FROM certificates WHERE user_id = ? AND event_id = ? LIMIT 1',
            [user_id, event_id]
        );

        if (existing.length) {

            return res.status(409).json({
                message: 'Já existe um certificado emitido para este usuário neste evento.',
                data: existing[0],
            });
        }

        const code = uuidv4();
        const issuedAt = new Date();


        const [result] = await db.execute(
            `INSERT INTO certificates
             (user_id, user_name, user_cpf, event_id, event_title, event_start_at, code, issued_at, pdf_url)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, '')`,
            [
                user_id,
                safeUserName,
                safeUserCpf,
                event_id,
                safeEventTitle,
                toMySqlDateTime(safeEventStart),
                code,
                toMySqlDateTime(issuedAt),
            ]
        );

        const id = result.insertId;


        const pdfFilename = `cert-${id}.pdf`;
        const pdfPath = path.join(storageDir, pdfFilename);

        const certForPdf = {
            id,
            user_id,
            user_name: safeUserName,
            user_cpf: safeUserCpf,
            event_id,
            event_title: safeEventTitle,
            event_start_at: safeEventStart,
            code,
            issued_at: issuedAt.toISOString(),
            pdf_url: '',
        };

        await renderCertificatePDF({
            user: { id: user_id, name: safeUserName, cpf: safeUserCpf },
            event: { id: event_id, title: safeEventTitle, start_at: safeEventStart },
            cert: certForPdf,
            outPath: pdfPath,
        });

        const baseUrl = process.env.PUBLIC_BASE_URL || 'http://localhost:4000';
        const pdfUrl = `${baseUrl}/certificates/files/${pdfFilename}`;


        await db.execute(
            'UPDATE certificates SET pdf_url = ? WHERE id = ?',
            [pdfUrl, id]
        );

        certForPdf.pdf_url = pdfUrl;

        return res.status(201).json({
            message: 'Certificado emitido com sucesso.',
            data: certForPdf,
        });
    } catch (err) {
        console.error('Erro ao gerar certificado:', err);
        return res.status(500).json({
            message: 'Erro ao gerar PDF do certificado.',
        });
    }
});



router.get('/:id', requireApiKey, async (req, res) => {
    try {
        const certId = Number(req.params.id);
        const [rows] = await db.execute(
            'SELECT * FROM certificates WHERE id = ? LIMIT 1',
            [certId]
        );

        if (!rows.length) {
            return res.status(404).json({ message: 'Certificado não encontrado.' });
        }

        return res.json({ data: rows[0] });
    } catch (err) {
        console.error('Erro ao buscar certificado:', err);
        return res.status(500).json({ message: 'Erro ao buscar certificado.' });
    }
});

export default router;
