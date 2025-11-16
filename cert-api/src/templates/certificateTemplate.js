// src/templates/certificateTemplate.js
import PDFDocument from 'pdfkit';
import fs from 'node:fs';

export async function renderCertificatePDF({ user, event, cert, outPath }) {
    return new Promise((resolve, reject) => {
        const doc = new PDFDocument({ size: 'A4', margin: 50 });
        const stream = fs.createWriteStream(outPath);
        doc.pipe(stream);

        const baseUrl = process.env.PUBLIC_BASE_URL || 'http://localhost:4000';


        const participantName = user?.name || 'Participante';
        const participantCpf  = user?.cpf || '';
        const eventTitle      = event?.title || 'Evento';
        const eventDate       = event?.start_at || new Date().toISOString();


        const pageWidth  = doc.page.width;
        const pageHeight = doc.page.height;
        const margin     = 72; // ~ 2,5 cm


        doc
            .lineWidth(2)
            .roundedRect(margin / 2, margin / 2, pageWidth - margin, pageHeight - margin, 10)
            .stroke();


        doc
            .font('Helvetica-Bold')
            .fontSize(28)
            .text('CERTIFICADO DE PARTICIPAÇÃO', margin, 120, {
                align: 'center',
            });


        doc
            .moveDown(1)
            .font('Helvetica')
            .fontSize(14)
            .text('Certificamos a participação no evento abaixo descrito.', {
                align: 'center',
            });

        doc.moveDown(3);


        const corpo =
            `Certificamos que ${participantName}` +
            (participantCpf ? ` (CPF ${maskCpf(participantCpf)})` : '') +
            ` participou do evento "${eventTitle}", ` +
            `realizado em ${formatDate(eventDate)}.`;

        doc
            .fontSize(14)
            .text(corpo, {
                align: 'justify',
                lineGap: 6,
            });

        doc.moveDown(2);


        doc
            .fontSize(11)
            .text(`Código de autenticidade: ${cert.code}`, {
                align: 'left',
            });

        doc
            .fontSize(11)
            .text(`Verifique a autenticidade em:`, {
                align: 'left',
            });

        doc
            .fillColor('blue')
            .text(`${baseUrl}/certificates/verify/${cert.code}`, {
                link: `${baseUrl}/certificates/verify/${cert.code}`,
                underline: true,
            })
            .fillColor('black');

        doc.moveDown(3);


        doc
            .fontSize(11)
            .text(`Emitido em: ${formatDateTime(cert.issued_at)}`, {
                align: 'right',
            });

        doc.moveDown(4);


        const lineWidth = 220;
        const lineX = (pageWidth - lineWidth) / 2;
        const lineY = doc.y;

        doc
            .moveTo(lineX, lineY)
            .lineTo(lineX + lineWidth, lineY)
            .stroke();

        doc
            .fontSize(11)
            .text('Responsável pelo evento', lineX, lineY + 5, {
                width: lineWidth,
                align: 'center',
            });


        doc.end();

        stream.on('finish', () => resolve(outPath));
        stream.on('error', reject);
    });
}

function maskCpf(cpf = '') {
    const s = String(cpf).replace(/\D/g, '');
    if (s.length !== 11) return cpf;
    return `${s.slice(0,3)}.${s.slice(3,6)}.${s.slice(6,9)}-${s.slice(9)}`;
}

function formatDate(isoOrStr) {
    const d = new Date(isoOrStr);
    if (Number.isNaN(d.getTime())) return isoOrStr;
    return d.toLocaleDateString('pt-BR');
}

function formatDateTime(isoOrStr) {
    const d = new Date(isoOrStr);
    if (Number.isNaN(d.getTime())) return isoOrStr;
    return d.toLocaleString('pt-BR');
}
