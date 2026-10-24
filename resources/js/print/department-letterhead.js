export const departmentLetterheadStyles = `
    .print-letterhead {
        text-align: center;
        margin-bottom: 10px;
        padding-bottom: 6px;
        border-bottom: 2px solid #111;
        color: #111;
        font-family: "Times New Roman", serif;
    }
    .print-letterhead__seal {
        display: block;
        width: 50px;
        height: 50px;
        object-fit: contain;
        margin: 0 auto 2px;
    }
    .print-letterhead__line { font-size: 11px; line-height: 1.15; }
    .print-letterhead__department { font-size: 13px; font-weight: 700; }
    .print-footer {
        display: flex;
        align-items: center;
        gap: 12px;
        border-top: 2px solid #111;
        padding-top: 4px;
        color: #111;
        font-family: Arial, sans-serif;
        break-inside: avoid;
        page-break-inside: avoid;
    }
    .print-page {
        position: relative;
        width: 180mm;
        height: 267mm;
        overflow: hidden;
        break-after: page;
        page-break-after: always;
    }
    .print-page:last-child {
        break-after: auto;
        page-break-after: auto;
    }
    .print-page__content {
        height: calc(100% - 18mm);
        overflow: hidden;
    }
    .print-page > .print-footer {
        position: absolute;
        right: 0;
        bottom: 0;
        left: 0;
        height: 17mm;
        margin: 0;
        background: #fff;
    }
    .print-footer__logos {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 0 0 auto;
    }
    .print-footer__logo {
        width: 56px;
        height: 50px;
        object-fit: contain;
    }
    .print-footer__contact {
        display: grid;
        grid-template-columns: repeat(2, max-content);
        gap: 2px 10px;
        font-size: 9px;
        line-height: 1.2;
    }
    @media print {
        .print-letterhead__seal,
        .print-footer__logo {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
`;

export const departmentLetterhead = (resolveAssetUrl) => `
    <header class="print-letterhead">
        <img class="print-letterhead__seal" src="${resolveAssetUrl('deped logo.png')}" alt="Department of Education seal">
        <div class="print-letterhead__line">Republic of the Philippines</div>
        <div class="print-letterhead__department">Department of Education</div>
        <div class="print-letterhead__line">REGION III - CENTRAL LUZON</div>
        <div class="print-letterhead__line">SCHOOLS DIVISION OFFICE OF NUEVA ECIJA</div>
    </header>
`;

export const departmentPrintFooter = (resolveAssetUrl) => `
    <footer class="print-footer">
        <div class="print-footer__logos">
            <img class="print-footer__logo" src="${resolveAssetUrl('DepEd-MATATAG.png')}" alt="DepEd MATATAG">
            <img class="print-footer__logo" src="${resolveAssetUrl('bagongpilipinas.jpg')}" alt="Bagong Pilipinas">
            <img class="print-footer__logo" src="${resolveAssetUrl('cnhs_logo.png')}" alt="Carranglan National High School">
        </div>
        <div class="print-footer__contact">
            <span>Address: Brgy. Rizal, Santa Rosa, Nueva Ecija 3101</span>
            <span>Telephone No.: (044) 940 3121</span>
            <span>Email: nueva.ecija@deped.gov.ph</span>
            <span>Facebook Page: DepEd SDO Nueva Ecija</span>
            <span>Webpage: www.deped-ne.net.ph</span>
        </div>
    </footer>
`;

export const paginatePrintDocument = (win, { groups, tableHeadHtml, footerHtml, trailerHtml = '' }) => {
    const doc = win.document;
    const root = doc.createElement('main');
    root.className = 'print-pages';
    doc.body.replaceChildren(root);

    const createPage = (prefixHtml) => {
        const page = doc.createElement('section');
        page.className = 'print-page';
        page.innerHTML = `
            <div class="print-page__content">
                ${prefixHtml}
                <table class="print-table">
                    <thead><tr>${tableHeadHtml}</tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            ${footerHtml}`;
        root.append(page);
        return {
            page,
            content: page.querySelector('.print-page__content'),
            body: page.querySelector('.print-table tbody'),
        };
    };

    let currentPage;
    groups.forEach((group) => {
        currentPage = createPage(group.prefixHtml);

        group.rows.forEach((rowHtml) => {
            const row = doc.createElement('tr');
            row.innerHTML = rowHtml;
            currentPage.body.append(row);

            if (currentPage.content.scrollHeight > currentPage.content.clientHeight) {
                row.remove();
                if (!currentPage.body.rows.length) {
                    throw new Error('A print row exceeds the available page content area.');
                }

                currentPage = createPage('');
                currentPage.body.append(row);
                if (currentPage.content.scrollHeight > currentPage.content.clientHeight) {
                    throw new Error('A print row exceeds the available page content area.');
                }
            }
        });
    });

    if (trailerHtml) {
        const trailer = doc.createElement('div');
        trailer.innerHTML = trailerHtml;
        currentPage.content.append(trailer);
        if (currentPage.content.scrollHeight > currentPage.content.clientHeight) {
            trailer.remove();
            currentPage = createPage('');
            currentPage.content.append(trailer);
            if (currentPage.content.scrollHeight > currentPage.content.clientHeight) {
                throw new Error('Print summary exceeds the available page content area.');
            }
        }
    }
};
