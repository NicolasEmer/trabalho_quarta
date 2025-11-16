import path from 'path';
import { pool } from '../db.js';
import { renderCertificatePDF } from '../templates/certificateTemplate.js';
import { v4 as uuidv4 } from 'uuid';
import fs from 'fs';

const STORAGE_DIR = path.resolve('storage/certificates');

export async function emitCertificate({ userId, eventId }) {

    const [users] = await pool.query('SELECT id, name, cpf, email FROM users WHERE id = ?', [userId]);
    if (users.length === 0) throw new Error('Usuário não encontrado');

    const [events] = await pool.query('SELECT id, title, start_at FROM events WHERE id = ?', [eventId]);
    if (events.length === 0) throw new Error('Evento não encontrado');

    const user = users[0];
    const event = events[0];


    const code = uuidv4().replace(/-/g, '').slice(0, 20).toUpperCase();
    const issuedAt = new Date();


    if (!fs.existsSync(STORAGE_DIR)) fs.mkdirSync(STORAGE_DIR, { recursive: true });


    const relPath = `${code}.pdf`;
    const pdfPath = path.join(STORAGE_DIR, relPath);

    await renderCertificatePDF({
        user,
        event,
        cert: { code, issued_at: issuedAt },
        outPath: pdfPath
    });


    const [result] = await pool.query(
        'INSERT INTO certificates (user_id, event_id, code, issued_at, pdf_path, metadata) VALUES (?,?,?,?,?,?)',
        [userId, eventId, code, issuedAt, relPath, JSON.stringify({ version: 1 })]
    );

    const id = result.insertId;

    return {
        id,
        user_id: userId,
        event_id: eventId,
        code,
        issued_at: issuedAt,
        pdf_url: `${process.env.PUBLIC_BASE_URL}/certificates/${id}/arquillian`
    };
}

export async function getCertificateById(id) {
    const [rows] = await pool.query('SELECT * FROM certificates WHERE id = ?', [id]);
    if (rows.length === 0) return null;
    const c = rows[0];
    return decorate(c);
}

export async function getCertificateByCode(code) {
    const [rows] = await pool.query('SELECT * FROM certificates WHERE code = ?', [code]);
    if (rows.length === 0) return null;
    const c = rows[0];
    return decorate(c);
}

function decorate(row) {
    return {
        id: row.id,
        user_id: row.user_id,
        event_id: row.event_id,
        code: row.code,
        issued_at: row.issued_at,
        pdf_url: `${process.env.PUBLIC_BASE_URL}/certificates/${row.id}/arquillian`,
        valid: true,
        metadata: row.metadata ? JSON.parse(row.metadata) : null,
        pdf_path: row.pdf_path
    };
}
